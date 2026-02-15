"""
Database manager for SNMP Worker.
Handles database connections and operations.
"""

from typing import Optional, List, Union
from datetime import datetime
from sqlalchemy import create_engine, and_
from sqlalchemy.orm import sessionmaker, Session
from sqlalchemy.pool import QueuePool
from contextlib import contextmanager
import logging

from models.database import (
    Base, SNMPDevice, DevicePollingData, PortStatusData,
    Alarm, AlarmHistory, DeviceStatus, PortStatus, AlarmStatus, AlarmSeverity
)
from config.config_loader import Config


class DatabaseManager:
    """Database manager for SNMP Worker."""
    
    def __init__(self, config: Config):
        """
        Initialize database manager.
        
        Args:
            config: Configuration object
        """
        self.config = config
        self.logger = logging.getLogger('snmp_worker.db')
        
        # Create engine
        db_url = config.get_database_url()
        self.engine = create_engine(
            db_url,
            poolclass=QueuePool,
            pool_size=config.database.pool_size,
            max_overflow=config.database.max_overflow,
            pool_pre_ping=True,
            echo=False
        )
        
        # Create session factory
        self.Session = sessionmaker(bind=self.engine)
        
        self.logger.info("Database manager initialized")
    
    @contextmanager
    def session_scope(self):
        """
        Provide a transactional scope around a series of operations.
        
        Yields:
            Database session
        """
        session = self.Session()
        try:
            yield session
            session.commit()
        except Exception as e:
            session.rollback()
            self.logger.error(f"Database error: {e}")
            raise
        finally:
            session.close()
    
    def get_or_create_device(
        self,
        session: Session,
        name: str,
        ip_address: str,
        vendor: str,
        model: str,
        **kwargs
    ) -> SNMPDevice:
        """
        Get existing device or create new one.
        
        Args:
            session: Database session
            name: Device name
            ip_address: Device IP
            vendor: Vendor name
            model: Model name
            **kwargs: Additional device attributes
            
        Returns:
            SNMPDevice instance
        """
        # First, try to find device by IP address
        device = session.query(SNMPDevice).filter_by(ip_address=ip_address).first()
        
        if device:
            if device.name != name:
                self.logger.info(f"Updating device name from '{device.name}' to '{name}' for IP {ip_address}")
            device.name = name
            device.vendor = vendor
            device.model = model
            for key, value in kwargs.items():
                if hasattr(device, key):
                    setattr(device, key, value)
        else:
            device_by_name = session.query(SNMPDevice).filter_by(name=name).first()
            if device_by_name:
                self.logger.info(f"Updating device IP from '{device_by_name.ip_address}' to '{ip_address}' for {name}")
                device = device_by_name
                device.ip_address = ip_address
                device.vendor = vendor
                device.model = model
                for key, value in kwargs.items():
                    if hasattr(device, key):
                        setattr(device, key, value)
            else:
                device = SNMPDevice(
                    name=name,
                    ip_address=ip_address,
                    vendor=vendor,
                    model=model,
                    **kwargs
                )
                session.add(device)
                session.flush()
                self.logger.info(f"Created new device: {name} ({ip_address})")
        
        return device
    
    def update_device_status(
        self,
        session: Session,
        device: SNMPDevice,
        status: DeviceStatus,
        system_description: Optional[str] = None,
        system_uptime: Optional[int] = None,
        total_ports: Optional[int] = None
    ) -> None:
        """
        Update device status and information.
        
        Args:
            session: Database session
            device: Device to update
            status: Device status
            system_description: System description
            system_uptime: System uptime in seconds
            total_ports: Total number of ports
        """
        device.status = status
        device.last_poll_time = datetime.utcnow()
        
        if status in [DeviceStatus.ONLINE]:
            device.last_successful_poll = datetime.utcnow()
            device.poll_failures = 0
        else:
            device.poll_failures += 1
        
        if system_description:
            device.system_description = system_description
        if system_uptime is not None:
            device.system_uptime = system_uptime
        if total_ports is not None:
            device.total_ports = total_ports
        
        device.updated_at = datetime.utcnow()
    
    def save_polling_data(
        self,
        session: Session,
        device: SNMPDevice,
        success: bool,
        poll_duration_ms: float,
        error_message: Optional[str] = None,
        **metrics
    ) -> DevicePollingData:
        """
        Save polling data.
        
        Args:
            session: Database session
            device: Device
            success: Whether polling was successful
            poll_duration_ms: Polling duration in milliseconds
            error_message: Error message if failed
            **metrics: Additional metrics
            
        Returns:
            DevicePollingData instance
        """
        polling_data = DevicePollingData(
            device_id=device.id,
            success=success,
            poll_duration_ms=poll_duration_ms,
            error_message=error_message,
            **metrics
        )
        session.add(polling_data)
        return polling_data
    
    def save_port_status(
        self,
        session: Session,
        device: SNMPDevice,
        port_number: int,
        admin_status: PortStatus,
        oper_status: PortStatus,
        **port_data
    ) -> PortStatusData:
        """
        Save or update port status.
        
        Args:
            session: Database session
            device: Device
            port_number: Port number
            admin_status: Administrative status
            oper_status: Operational status
            **port_data: Additional port data
            
        Returns:
            PortStatusData instance
        """
        existing_port = session.query(PortStatusData).filter_by(
            device_id=device.id,
            port_number=port_number
        ).order_by(PortStatusData.poll_timestamp.desc()).first()
        
        port_status = PortStatusData(
            device_id=device.id,
            port_number=port_number,
            admin_status=admin_status,
            oper_status=oper_status,
            poll_timestamp=datetime.utcnow(),
            **port_data
        )
        
        if existing_port:
            port_status.first_seen = existing_port.first_seen
        
        session.add(port_status)
        return port_status
    
    def get_or_create_alarm(
        self,
        session: Session,
        device: SNMPDevice,
        alarm_type: str,
        severity: Union[AlarmSeverity, str],
        title: str,
        message: str,
        port_number: Optional[int] = None,
        old_value: Optional[str] = None,
        new_value: Optional[str] = None,
        mac_address: Optional[str] = None
    ) -> tuple[Alarm, bool]:
        """
        Get existing active alarm or create new one.
        Only creates new alarm if details are different from latest active alarm.
        
        Args:
            session: Database session
            device: Device
            alarm_type: Type of alarm
            severity: Alarm severity (AlarmSeverity enum or string)
            title: Alarm title
            message: Alarm message
            port_number: Port number (for port-specific alarms)
            old_value: Old value (for change tracking)
            new_value: New value (for change tracking)
            mac_address: MAC address (for MAC-related alarms)
            
        Returns:
            Tuple of (Alarm instance, is_new)
        """
        # Normalize severity to AlarmSeverity enum
        if isinstance(severity, str):
            severity_upper = severity.upper()
            
            # Manual mapping
            if severity_upper == "CRITICAL":
                severity = AlarmSeverity.CRITICAL
            elif severity_upper == "HIGH":
                severity = AlarmSeverity.HIGH
            elif severity_upper == "MEDIUM":
                severity = AlarmSeverity.MEDIUM
            elif severity_upper == "LOW":
                severity = AlarmSeverity.LOW
            elif severity_upper == "INFO":
                severity = AlarmSeverity.INFO
            else:
                severity = AlarmSeverity.MEDIUM
        
        elif not isinstance(severity, AlarmSeverity):
            severity = AlarmSeverity.MEDIUM
        
        # Check for existing ACTIVE alarm with same type and port
        query = session.query(Alarm).filter(
            and_(
                Alarm.device_id == device.id,
                Alarm.alarm_type == alarm_type,
                Alarm.status == AlarmStatus.ACTIVE
            )
        )
        
        if port_number is not None:
            query = query.filter(Alarm.port_number == port_number)
        
        # Get the most recent alarm
        existing_alarm = query.order_by(Alarm.created_at.desc()).first()
        
        # If there's an existing alarm, check if details are different
        if existing_alarm:
            # Compare old_value and new_value to determine if this is a different change
            details_changed = False
            
            # Check if both alarms have change details
            if old_value is not None or new_value is not None or mac_address is not None:
                # Check if the change details are different
                if (old_value != existing_alarm.old_value or 
                    new_value != existing_alarm.new_value or
                    mac_address != existing_alarm.mac_address):
                    details_changed = True
                    self.logger.info(
                        f"Creating new alarm - details changed: "
                        f"Old: ({existing_alarm.old_value} -> {existing_alarm.new_value}, MAC: {existing_alarm.mac_address}), "
                        f"New: ({old_value} -> {new_value}, MAC: {mac_address})"
                    )
            
            if not details_changed:
                # Same change - just increment occurrence count
                existing_alarm.occurrence_count += 1
                existing_alarm.last_occurrence = datetime.utcnow()
                existing_alarm.updated_at = datetime.utcnow()
                
                self.logger.info(
                    f"Updated existing alarm (ID={existing_alarm.id}), "
                    f"occurrence_count={existing_alarm.occurrence_count}"
                )
                return existing_alarm, False
        
        # Create new alarm (either no existing alarm or details are different)
        alarm = Alarm(
            device_id=device.id,
            alarm_type=alarm_type,
            severity=severity,
            title=title,
            message=message,
            port_number=port_number,
            status=AlarmStatus.ACTIVE,
            old_value=old_value,
            new_value=new_value,
            mac_address=mac_address
        )
        session.add(alarm)
        session.flush()
        
        # Add to history
        history = AlarmHistory(
            alarm_id=alarm.id,
            old_status=None,
            new_status=AlarmStatus.ACTIVE,
            change_reason="Alarm created",
            change_message=message
        )
        session.add(history)
        
        self.logger.info(f"Created new alarm: ID={alarm.id}, type={alarm_type}, port={port_number}")
        return alarm, True
    
    def resolve_alarm(
        self,
        session: Session,
        alarm: Alarm,
        reason: str = "Condition cleared"
    ) -> None:
        """
        Resolve an alarm.
        
        Args:
            session: Database session
            alarm: Alarm to resolve
            reason: Reason for resolution
        """
        if alarm.status != AlarmStatus.RESOLVED:
            old_status = alarm.status
            alarm.status = AlarmStatus.RESOLVED
            alarm.resolved_at = datetime.utcnow()
            
            # Add to history
            history = AlarmHistory(
                alarm_id=alarm.id,
                old_status=old_status,
                new_status=AlarmStatus.RESOLVED,
                change_reason=reason
            )
            session.add(history)
            self.logger.info(f"Resolved alarm: {alarm.alarm_type} for device ID {alarm.device_id}")
    
    def get_active_alarms(
        self,
        session: Session,
        device: Optional[SNMPDevice] = None
    ) -> List[Alarm]:
        """
        Get active alarms.
        
        Args:
            session: Database session
            device: Optional device filter
            
        Returns:
            List of active alarms
        """
        query = session.query(Alarm).filter(Alarm.status == AlarmStatus.ACTIVE)
        
        if device:
            query = query.filter(Alarm.device_id == device.id)
        
        return query.all()
    
    def cleanup_old_data(
        self,
        session: Session,
        days: int = 30
    ) -> None:
        """
        Clean up old polling data.
        
        Args:
            session: Database session
            days: Number of days to keep
        """
        from datetime import timedelta
        cutoff_date = datetime.utcnow() - timedelta(days=days)
        
        # Delete old polling data
        session.query(DevicePollingData).filter(
            DevicePollingData.poll_timestamp < cutoff_date
        ).delete()
        
        self.logger.info(f"Cleanup: Deleted data older than {cutoff_date}")