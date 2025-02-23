#!/bin/bash
#
# script: rc.S
#
# System initialization script.
# Mostly written by: Patrick J. Volkerding, <volkerdi@slackware.com>
#
# LimeTech - modified for Unraid OS
# Bergware - modified for Unraid OS, October 2023

# Set the path.
PATH=/usr/local/sbin:/usr/sbin:/sbin:/usr/local/bin:/usr/bin:/bin

# Mount /proc if it is not already mounted:
if [[ ! -d /proc/sys ]]; then
  /sbin/mount -v proc /proc -n -t proc 2>/dev/null
fi

# Mount /sys if it is not already mounted:
if [[ ! -d /sys/kernel ]]; then
  /sbin/mount -v sysfs /sys -n -t sysfs 2>/dev/null
fi

# The efivarfs filesystem is used for reading and writing EFI variables, such
# as the boot menu entries. By default efivarfs will be mounted read-write on
# the /sys/firmware/efi/efivars directory. To modify this behavior, edit the
# file: /etc/default/efivarfs
# Only try to mount if this directory exists (so the kernel supports efivarfs):
if [[ -d /sys/firmware/efi/efivars ]]; then
  # Only try to mount if efivarfs is not already mounted:
  if ! /sbin/mount | /bin/grep -wq efivarfs; then
    # Mount according to /etc/default/efivarfs:
    if [[ -r /etc/default/efivarfs ]]; then
      . /etc/default/efivarfs
    else # default
      EFIVARFS="rw"
    fi
    case "$EFIVARFS" in
    'rw')
      /sbin/mount -o rw -t efivarfs none /sys/firmware/efi/efivars
      ;;
    'ro')
      /sbin/mount -o ro -t efivarfs none /sys/firmware/efi/efivars
      ;;
    esac
  fi
fi

# If /run exists, mount a tmpfs on it (unless the
# initrd has already done so):
if [[ -d /run ]]; then
  if ! /bin/grep -wq "tmpfs /run tmpfs" /proc/mounts; then
    /sbin/mount -v -n -t tmpfs tmpfs /run -o mode=0755,size=128M,nodev,nosuid,noexec
  fi
fi

# LimeTech - lets mount debugfs
/sbin/mount -v -t debugfs none /sys/kernel/debug

# LimeTech - determine if the 'unraidlabel' kernel append parameter was
# provided to override which device is mounted for /boot (default: UNRAID)
UNRAIDLABEL="UNRAID"
UNRAIDROOT=
set -- $(/bin/cat /proc/cmdline)
for x in "$@"; do
  case "$x" in
  unraidlabel=*)
    UNRAIDLABEL="${x#unraidlabel=}"
    ;;
  root=*)
    UNRAIDROOT="${x#root=}"
    ;;
  esac
done

# LimeTech - poll for device with $UNRAIDLABEL present, with 30-sec timeout
# this serves to synchronize this script with USB subsystem
abort() {
  /bin/umount -a
  read -p "$1 - press ENTER key to reboot..."
  /bin/echo
  /sbin/reboot -fd
}

find_device() {
  # find which USB flash device/partition has the indicated label
  local i
  for i in {1..30}; do
    DEVICE=$(/sbin/blkid -L $UNRAIDLABEL)
    [[ -z $DEVICE ]] && /bin/sleep 1 || return 0
  done
  return 1
}

/bin/echo -n "waiting up to 30 sec for device with label $UNRAIDLABEL to come online ... "
find_device && /bin/echo "found $DEVICE" || abort "not found"

# detect filesystem from boot device
if /sbin/blkid -s TYPE $DEVICE | /bin/grep -q "btrfs" ; then
  NONVFAT=btrfs
  /sbin/mount -v -t btrfs -o auto,rw,noatime,nodiratime,degraded,discard=sync $DEVICE /boot || abort "cannot mount $DEVICE"
elif /sbin/blkid -s TYPE $DEVICE | /bin/grep -q "xfs" ; then
  NONVFAT=xfs
  /sbin/mount -v -t xfs -o auto,rw,noatime,nodiratime,discard $DEVICE /boot || abort "cannot mount $DEVICE"
else
  /bin/echo "Checking $DEVICE ..."
  FSCK=$(/sbin/fsck.fat -a -w $DEVICE 2>/dev/null)
  if [[ "$FSCK" == *"differences between boot sector and its backup"* ]] ; then
    /sbin/fsck.fat -w $DEVICE 2>/dev/null <<< "1"
  else
    echo "$FSCK"
  fi
  /sbin/mount -v -t vfat -o auto,rw,flush,noatime,nodiratime,dmask=77,fmask=177,shortname=mixed $DEVICE /boot || abort "cannot mount $DEVICE"
fi

# check initial files used to boot
bzcheck(){
  local BZFILE=$1
  if [[ -f /boot/config/skipbzcheck ]]; then
    /bin/echo "Skipping $BZFILE checksum verification"
    return
  fi
  /bin/echo "Verifying $BZFILE checksum ..."
  [[ -f "/boot/$BZFILE" ]] || abort "$BZFILE not present"
  local BZFILECHK="$BZFILE.sha256"
  [[ -f "/boot/$BZFILECHK" ]] || abort "$BZFILECHK not present"
  local HASH1=$(/bin/sha256sum /boot/$BZFILE)
  local HASH2=$(/bin/cat /boot/$BZFILECHK)
  [[ ${HASH1:0:64} != ${HASH2:0:64} ]] && abort "$BZFILE checksum error"
}

bzmount(){
  local BZDIR=$1
  local BZFILE=$2
  local MNTDIR=$3
  /sbin/mount -v -r -t squashfs /$BZDIR/$BZFILE /$MNTDIR || abort "cannot mount /$BZDIR/$BZFILE"
  # setup an overlayfs
  /bin/mkdir -p /var/local/overlay/$MNTDIR
  /bin/mkdir -p /var/local/overlay-work/$MNTDIR
  /sbin/mount -v -t overlay overlay -o lowerdir=/$MNTDIR,upperdir=/var/local/overlay/$MNTDIR,workdir=/var/local/overlay-work/$MNTDIR /$MNTDIR
}

if [[ $UNRAIDROOT == "" ]]; then
  bzcheck "bzimage"
  bzcheck "bzroot"
  bzcheck "bzroot-gui"
  bzcheck "bzmodules"
  bzcheck "bzfirmware"
  if [[ -f /boot/config/fastusr ]]; then
    /bin/cp /boot/bzfirmware /usr
    bzmount "usr" "bzfirmware" "usr"
  else
    bzmount "boot" "bzfirmware" "usr"
  fi
  bzmount "boot" "bzmodules" "lib"

  # now that /usr is mounted make /etc/rc.d a symlink
  /bin/rm -rf /etc/rc.d
  /bin/ln -sf /usr/local/etc/rc.d /etc

  # move /var/log to a tmpfs
  /bin/mv -f /var/log/* /var/empty
  /sbin/mount -t tmpfs -o size=128m,mode=0755 tmpfs /var/log
  /bin/mv -f /var/empty/* /var/log
else
  /bin/echo "Checking root filesystem"
  /sbin/fsck -C -a $UNRAIDROOT
  RETVAL=$?
  [[ $RETVAL -ge 2 ]] && abort "fsck failed with return value $RETVAL"
  # Remount the root filesystem in read-write mode
  /bin/echo "Remounting $UNRAIDROOT with read-write enabled."
  /sbin/mount -w -v -n -o remount /
  RETVAL=$?
  [[ $RETVAL -gt 0 ]] && abort "failed to remount $UNRAIDROOT r/w with return value $RETVAL"
  [[ ! -f /etc/rc.d/rc.S.cont ]] && abort "unable to continue - you must remove 'root=$UNRAIDROOT' from syslinux.cfg"
fi

# set permissions for non vfat boot on /boot
if [[ $NONVFAT == btrfs || $NONVFAT == xfs ]]; then
  /usr/bin/chown -R root:root /boot
  /usr/bin/chmod -R 644 /boot
fi

. /etc/unraid-version
# log all PHP warnings (for beta|rc releases)
if [[ -f /etc/php.d/errors-php.ini && $version =~ -(beta|rc) ]]; then
  sed -i 's/^error_reporting=.*/error_reporting=E_ALL/' /etc/php.d/errors-php.ini
fi
# invoke testing hook (for -(beta|rc).x.y releases)
if [[ -f /boot/config/rc.S.extra && $version =~ -(beta|rc)\.[0-9]+\.[0-9]+$ ]]; then
  . /boot/config/rc.S.extra
fi
# and continue in separate script
. /etc/rc.d/rc.S.cont
