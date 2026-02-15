"""
Port Change Detector - Tracks and detects changes in port configurations.
Monitors MAC addresses, VLANs, descriptions, and creates alarms for changes.
"""

import logging
import json
from typing import Optional, Dict, List, Tuple, Any
from datetime import datetime, timedelta
from sqlalchemy.orm import Session
from sqlalchemy import and_, or_

from models.database import (
    SNMPDevice, PortStatusData, PortSnapshot, MACAddressTracking,
    PortChangeHistory, Alarm, AlarmSeverity, AlarmStatus, ChangeType
)
from core.database_manager import DatabaseManager
from core.alarm_manager import AlarmManager


class PortChangeDetector:
    """
    Detects and tracks changes in port configurations.
    Compares current state with previous snapshots to identify changes.
    """
    
    def __init__(
        self,
        db_manager: DatabaseManager,
        alarm_manager: AlarmManager
    ):
        """
        Initialize port change detector.
        
        Args:
            db_manager: Database manager
            alarm_manager: Alarm manager
        """
        self.db_manager = db_manager
        self.alarm_manager = alarm_manager
        self.logger = logging.getLogger('snmp_worker.change_detector')
        
        self.logger.info("Port Change Detector initialized")
    
    def detect_and_record_changes(
        self,
        session: Session,
        device: SNMPDevice,
        current_port_data: PortStatusData
    ) -> List[PortChangeHistory]:
        """
        Detect changes for a specific port and record them.
        
        Args:
            session: Database session
            device: Device
            current_port_data: Current port status data
            
        Returns:
            List of detected changes
        """
        changes = []
        
        # Get the previous snapshot for this port
        previous_snapshot = self._get_latest_snapshot(
            session,
            device.id,
            current_port_data.port_number
        )
        
        if not previous_snapshot:
            # First time seeing this port, create initial snapshot
            self._create_snapshot(session, device, current_port_data)
            self.logger.debug(f"Created initial snapshot for {device.name} port {current_port_data.port_number}")
            return changes
        
        # Check for MAC address changes
        mac_changes = self._detect_mac_changes(
            session,
            device,
            current_port_data,
            previous_snapshot
        )
        changes.extend(mac_changes)
        
        # Check for VLAN changes
        vlan_change = self._detect_vlan_change(
            session,
            device,
            current_port_data,
            previous_snapshot
        )
        if vlan_change:
            changes.append(vlan_change)
        
        # Check for description changes
        desc_change = self._detect_description_change(
            session,
            device,
            current_port_data,
            previous_snapshot
        )
        if desc_change:
            changes.append(desc_change)
        
        # Check for status changes
        status_change = self._detect_status_change(
            session,
            device,
            current_port_data,
            previous_snapshot
        )
        if status_change:
            changes.append(status_change)
        
        # Create new snapshot
        self._create_snapshot(session, device, current_port_data)
        
        return changes
    
    def _get_latest_snapshot(
        self,
        session: Session,
        device_id: int,
        port_number: int
    ) -> Optional[PortSnapshot]:
        """Get the latest snapshot for a port."""
        return session.query(PortSnapshot).filter(
            and_(
                PortSnapshot.device_id == device_id,
                PortSnapshot.port_number == port_number
            )
        ).order_by(PortSnapshot.snapshot_timestamp.desc()).first()
    
    def _create_snapshot(
        self,
        session: Session,
        device: SNMPDevice,
        port_data: PortStatusData
    ) -> PortSnapshot:
        """Create a new port snapshot."""
        snapshot = PortSnapshot(
            device_id=device.id,
            port_number=port_data.port_number,
            snapshot_timestamp=datetime.utcnow(),
            port_name=port_data.port_name,
            port_alias=port_data.port_alias,
            port_description=port_data.port_description,
            admin_status=port_data.admin_status.value if port_data.admin_status else None,
            oper_status=port_data.oper_status.value if port_data.oper_status else None,
            vlan_id=port_data.vlan_id,
            vlan_name=port_data.vlan_name,
            mac_address=port_data.mac_address,
            mac_addresses=port_data.mac_addresses
        )
        session.add(snapshot)
        return snapshot
    
    def _detect_mac_changes(
        self,
        session: Session,
        device: SNMPDevice,
        current: PortStatusData,
        previous: PortSnapshot
    ) -> List[PortChangeHistory]:
        """Detect MAC address changes (added, removed, moved)."""
        changes = []
        
        # Parse current MAC addresses
        current_macs = self._parse_mac_addresses(
            current.mac_address,
            current.mac_addresses
        )
        
        # Parse previous MAC addresses
        previous_macs = self._parse_mac_addresses(
            previous.mac_address,
            previous.mac_addresses
        )
        
        # Detect new MACs
        new_macs = current_macs - previous_macs
        for mac in new_macs:
            change = self._handle_mac_added_or_moved(
                session,
                device,
                current.port_number,
                mac,
                current.vlan_id
            )
            if change:
                changes.append(change)
        
        # Detect removed MACs
        removed_macs = previous_macs - current_macs
        for mac in removed_macs:
            change = self._handle_mac_removed(
                session,
                device,
                current.port_number,
                mac
            )
            if change:
                changes.append(change)
        
        return changes
    
    def _parse_mac_addresses(
        self,
        mac_address: Optional[str],
        mac_addresses: Optional[str]
    ) -> set:
        """Parse MAC addresses from database fields."""
        macs = set()
        
        if mac_address:
            macs.add(mac_address.upper())
        
        if mac_addresses:
            try:
                mac_list = json.loads(mac_addresses)
                for mac in mac_list:
                    if mac:
                        macs.add(mac.upper())
            except (json.JSONDecodeError, TypeError):
                pass
        
        return macs
    
    def _handle_mac_added_or_moved(
        self,
        session: Session,
        device: SNMPDevice,
        port_number: int,
        mac_address: str,
        vlan_id: Optional[int]
    ) -> Optional[PortChangeHistory]:
        """Handle a MAC address that was added or moved to a port."""
        
        # Check if MAC exists in tracking table
        mac_tracking = session.query(MACAddressTracking).filter(
            MACAddressTracking.mac_address == mac_address
        ).first()
        
        if mac_tracking:
            # MAC exists - check if it moved
            if (mac_tracking.current_device_id != device.id or
                mac_tracking.current_port_number != port_number):
                
                # MAC moved!
                old_device = None
                if mac_tracking.current_device_id:
                    old_device = session.query(SNMPDevice).filter(
                        SNMPDevice.id == mac_tracking.current_device_id
                    ).first()
                
                change = self._record_mac_moved(
                    session,
                    mac_address,
                    old_device,
                    mac_tracking.current_port_number,
                    device,
                    port_number,
                    vlan_id
                )
                
                # Update MAC tracking
                mac_tracking.previous_device_id = mac_tracking.current_device_id
                mac_tracking.previous_port_number = mac_tracking.current_port_number
                mac_tracking.current_device_id = device.id
                mac_tracking.current_port_number = port_number
                mac_tracking.current_vlan_id = vlan_id
                mac_tracking.last_moved = datetime.utcnow()
                mac_tracking.last_seen = datetime.utcnow()
                mac_tracking.move_count += 1
                
                return change
            else:
                # Same location, just update last_seen
                mac_tracking.last_seen = datetime.utcnow()
                return None
        else:
            # New MAC - create tracking entry
            mac_tracking = MACAddressTracking(
                mac_address=mac_address,
                current_device_id=device.id,
                current_port_number=port_number,
                current_vlan_id=vlan_id,
                first_seen=datetime.utcnow(),
                last_seen=datetime.utcnow(),
                move_count=0
            )
            session.add(mac_tracking)
            
            # Record as new MAC
            change = self._record_mac_added(
                session,
                device,
                port_number,
                mac_address,
                vlan_id
            )
            return change
    
    def _handle_mac_removed(
        self,
        session: Session,
        device: SNMPDevice,
        port_number: int,
        mac_address: str
    ) -> Optional[PortChangeHistory]:
        """Handle a MAC address that was removed from a port."""
        
        # Update MAC tracking - set current location to null
        mac_tracking = session.query(MACAddressTracking).filter(
            MACAddressTracking.mac_address == mac_address
        ).first()
        
        if mac_tracking:
            mac_tracking.previous_device_id = mac_tracking.current_device_id
            mac_tracking.previous_port_number = mac_tracking.current_port_number
            mac_tracking.current_device_id = None
            mac_tracking.current_port_number = None
            mac_tracking.last_seen = datetime.utcnow()
        
        # Record the removal
        change = PortChangeHistory(
            device_id=device.id,
            port_number=port_number,
            change_type=ChangeType.MAC_REMOVED,
            change_timestamp=datetime.utcnow(),
            old_mac_address=mac_address,
            change_details=f"MAC address {mac_address} removed from port {port_number}"
        )
        session.add(change)
        
        return change
    
    def _record_mac_moved(
        self,
        session: Session,
        mac_address: str,
        old_device: Optional[SNMPDevice],
        old_port: Optional[int],
        new_device: SNMPDevice,
        new_port: int,
        vlan_id: Optional[int]
    ) -> PortChangeHistory:
        """Record a MAC address movement and create alarm."""
        
        old_device_name = old_device.name if old_device else "Unknown"
        old_port_str = str(old_port) if old_port else "Unknown"
        
        change_details = (
            f"MAC {mac_address} moved from {old_device_name} port {old_port_str} "
            f"to {new_device.name} port {new_port}"
        )
        
        # Create change history entry
        change = PortChangeHistory(
            device_id=new_device.id,
            port_number=new_port,
            change_type=ChangeType.MAC_MOVED,
            change_timestamp=datetime.utcnow(),
            old_mac_address=mac_address,
            new_mac_address=mac_address,
            from_device_id=old_device.id if old_device else None,
            from_port_number=old_port,
            to_device_id=new_device.id,
            to_port_number=new_port,
            new_vlan_id=vlan_id,
            change_details=change_details
        )
        session.add(change)
        session.flush()
        
        # Create alarm for MAC movement
        alarm, is_new = self.db_manager.get_or_create_alarm(
            session,
            new_device,
            "mac_moved",
            "HIGH",
            f"MAC {mac_address} moved to port {new_port}",
            change_details,
            port_number=new_port
        )
        
        if alarm:
            change.alarm_created = True
            change.alarm_id = alarm.id
            
            # Add MAC address and change details to alarm
            alarm.mac_address = mac_address
            alarm.old_value = f"{old_device_name} port {old_port_str}"
            alarm.new_value = f"{new_device.name} port {new_port}"
            
            # Send notifications
            if is_new:
                self.alarm_manager._send_notifications(
                    new_device,
                    "mac_moved",
                    "HIGH",
                    change_details,
                    port_number=new_port,
                    port_name=f"Port {new_port}"
                )
                alarm.notification_sent = True
                alarm.last_notification_sent = datetime.utcnow()
        
        self.logger.warning(change_details)
        
        return change
    
    def _record_mac_added(
        self,
        session: Session,
        device: SNMPDevice,
        port_number: int,
        mac_address: str,
        vlan_id: Optional[int]
    ) -> PortChangeHistory:
        """Record a new MAC address on a port."""
        
        change_details = f"New MAC {mac_address} detected on {device.name} port {port_number}"
        
        change = PortChangeHistory(
            device_id=device.id,
            port_number=port_number,
            change_type=ChangeType.MAC_ADDED,
            change_timestamp=datetime.utcnow(),
            new_mac_address=mac_address,
            new_vlan_id=vlan_id,
            change_details=change_details
        )
        session.add(change)
        
        self.logger.info(change_details)
        
        return change
    
    def _detect_vlan_change(
        self,
        session: Session,
        device: SNMPDevice,
        current: PortStatusData,
        previous: PortSnapshot
    ) -> Optional[PortChangeHistory]:
        """Detect VLAN changes."""
        
        if current.vlan_id != previous.vlan_id:
            change_details = (
                f"VLAN changed on {device.name} port {current.port_number} "
                f"from {previous.vlan_id or 'None'} to {current.vlan_id or 'None'}"
            )
            
            change = PortChangeHistory(
                device_id=device.id,
                port_number=current.port_number,
                change_type=ChangeType.VLAN_CHANGED,
                change_timestamp=datetime.utcnow(),
                old_vlan_id=previous.vlan_id,
                new_vlan_id=current.vlan_id,
                old_value=previous.vlan_name,
                new_value=current.vlan_name,
                change_details=change_details
            )
            session.add(change)
            session.flush()
            
            # Create alarm for VLAN change
            alarm, is_new = self.db_manager.get_or_create_alarm(
                session,
                device,
                "vlan_changed",
                "MEDIUM",
                f"VLAN changed on port {current.port_number}",
                change_details,
                port_number=current.port_number
            )
            
            if alarm:
                change.alarm_created = True
                change.alarm_id = alarm.id
                alarm.old_value = str(previous.vlan_id or 'None')
                alarm.new_value = str(current.vlan_id or 'None')
            
            self.logger.info(change_details)
            
            return change
        
        return None
    
    def _detect_description_change(
        self,
        session: Session,
        device: SNMPDevice,
        current: PortStatusData,
        previous: PortSnapshot
    ) -> Optional[PortChangeHistory]:
        """Detect port description changes."""
        
        current_desc = current.port_alias or current.port_description or ""
        previous_desc = previous.port_alias or previous.port_description or ""
        
        if current_desc != previous_desc:
            change_details = (
                f"Description changed on {device.name} port {current.port_number} "
                f"from '{previous_desc}' to '{current_desc}'"
            )
            
            change = PortChangeHistory(
                device_id=device.id,
                port_number=current.port_number,
                change_type=ChangeType.DESCRIPTION_CHANGED,
                change_timestamp=datetime.utcnow(),
                old_description=previous_desc,
                new_description=current_desc,
                change_details=change_details
            )
            session.add(change)
            session.flush()
            
            # Create alarm for description change
            alarm, is_new = self.db_manager.get_or_create_alarm(
                session,
                device,
                "description_changed",
                "MEDIUM",
                f"Description changed on port {current.port_number}",
                change_details,
                port_number=current.port_number
            )
            
            if alarm:
                change.alarm_created = True
                change.alarm_id = alarm.id
                alarm.old_value = previous_desc or '(empty)'
                alarm.new_value = current_desc or '(empty)'
            
            self.logger.info(change_details)
            
            return change
        
        return None
    
    def _detect_status_change(
        self,
        session: Session,
        device: SNMPDevice,
        current: PortStatusData,
        previous: PortSnapshot
    ) -> Optional[PortChangeHistory]:
        """Detect operational status changes."""
        
        current_status = current.oper_status.value if current.oper_status else None
        previous_status = previous.oper_status
        
        if current_status != previous_status:
            change_details = (
                f"Status changed on {device.name} port {current.port_number} "
                f"from {previous_status} to {current_status}"
            )
            
            change = PortChangeHistory(
                device_id=device.id,
                port_number=current.port_number,
                change_type=ChangeType.STATUS_CHANGED,
                change_timestamp=datetime.utcnow(),
                old_value=previous_status,
                new_value=current_status,
                change_details=change_details
            )
            session.add(change)
            
            self.logger.info(change_details)
            
            return change
        
        return None
    
    def cleanup_old_snapshots(self, session: Session, days: int = 30) -> int:
        """
        Clean up snapshots older than specified days.
        
        Args:
            session: Database session
            days: Number of days to keep
            
        Returns:
            Number of snapshots deleted
        """
        cutoff_date = datetime.utcnow() - timedelta(days=days)
        
        deleted = session.query(PortSnapshot).filter(
            PortSnapshot.snapshot_timestamp < cutoff_date
        ).delete()
        
        self.logger.info(f"Cleaned up {deleted} old port snapshots")
        
        return deleted
