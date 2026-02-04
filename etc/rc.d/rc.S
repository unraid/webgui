#!/bin/bash
#
# script: rc.S
#
# System initialization script.
# Mostly written by: Patrick J. Volkerding, <volkerdi@slackware.com>
#
# LimeTech - modified for Unraid OS
# Bergware - modified for Unraid OS, October 2023

# functions to echo command to console and dmesg and then run it
log() {
  echo "rc.S: $@" | tee >/dev/kmsg
}
run(){
  log "$@"
  "$@" &>/dev/null
}

# Set the path.
PATH=/usr/local/sbin:/usr/sbin:/sbin:/usr/local/bin:/usr/bin:/bin

# Mount /dev on a devtmpfs
/bin/mkdir -p /dev
/sbin/mount -n -t devtmpfs devtmpfs /dev

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

# LimeTech - mount debugfs
/sbin/mount -v -t debugfs none /sys/kernel/debug

# function to terminate the boot sequence
abort() {
  /bin/umount -a
  read -p "$1 - press ENTER key to reboot..."
  echo
  /sbin/reboot -fd
}

# LimeTech - check for unraid-specific parameters passed on kernel command line
TIMEOUT="30"
LABEL="UNRAID"
UUID=""
ROOT=""
VERS=""
UNRAIDBOOT="" # this is deprecated
set -- $(/bin/cat /proc/cmdline)
for x in "$@"; do
  case "$x" in
  unraidtimeout=*)
    TIMEOUT="${x#unraidtimeout=}"
    ;;
  unraidlabel=*)
    LABEL="${x#unraidlabel=}"
    ;;
  unraiduuid=*)
    UUID="${x#unraiduuid=}"
    ;;
  unraidvers=*)
    VERS="${x#unraidvers=}"
    ;;
  unraidboot=*)
    UNRAIDBOOT="${x#unraidboot=}"
    ;;
  root=*)
    ROOT="${x#root=}"
    ;;
  esac
done

# find which device/partition has the label LABEL or uuid UUID 
DEVICE=""
FSTYPE=""

find_device() {
  # we're going to need this
  /sbin/modprobe zfs
  # wait for at least one device to come online
  for (( ; TIMEOUT > 0; TIMEOUT-- )); do
    if [[ -n "$UNRAIDBOOT" ]]; then
      DEVICE=$(/sbin/blkid -L "$UNRAIDBOOT")
    elif [[ -n "$UUID" ]]; then
      DEVICE=$(/sbin/blkid -U "$UUID")
    else
      DEVICE=$(/sbin/blkid -L "$LABEL")
    fi
    if [[ -n $DEVICE ]]; then
      FSTYPE=$(/sbin/blkid -s TYPE -o value "$DEVICE")
      log "found $DEVICE with file system $FSTYPE"
      if [[ "$FSTYPE" == "zfs_member" ]]; then
        if zpool import -d $DEVICE -N "$UUID" ; then
          POOL=$(zpool list -H -o name)
          for (( ; TIMEOUT > 0; TIMEOUT-- )); do
            if zpool status -x "$POOL" ; then
              log "pool is healthy"
              return 0
            fi
            echo "Timeout in $TIMEOUT"
            /bin/sleep 1
          done
          log "pool is degraded"
          return 0
        else
          log "zpool import failed"
          return 1
        fi
      fi
      log "found $DEVICE"
      return 0
    fi
    echo "Timeout in $TIMEOUT"
    /bin/sleep 1
  done
  # timeout - no device found
  return 1
}  

# LimeTech - poll for boot device(s)
# this serves to synchronize this script with kernel device discovery
log "waiting up to $TIMEOUT sec for boot device(s) to come online ... "
find_device || abort "not found"

# mount detected device(s) on /boot
if [[ "$FSTYPE" = "zfs_member" ]]; then
  run /sbin/zfs set -u mountpoint=/boot "$POOL/boot"
  run /sbin/zfs mount -o atime=off "$POOL/boot" || abort "mount failure"

elif [[ "$FSTYPE" = "vfat" ]]; then
  log "Checking $DEVICE ..."
  FSCK=$(/sbin/fsck.fat -a -w $DEVICE 2>/dev/null)
  if [[ "$FSCK" == *"differences between boot sector and its backup"* ]] ; then
    /sbin/fsck.fat -w $DEVICE 2>/dev/null <<< "1"
  else
    log "$FSCK"
  fi
  /sbin/mount -v -t vfat -o auto,rw,flush,noatime,nodiratime,dmask=77,fmask=177,shortname=mixed $DEVICE /boot || abort "mount failure"

else
  abort "unsupported file system $FSTYPE"
fi

# check initial files used to boot
bzcheck(){
  local BZDIR="$1"
  local BZFILE="$2"
  if [[ -f /boot/config/skipbzcheck ]]; then
    log "Skipping $BZFILE checksum verification"
    return
  fi
  log "Verifying $BZDIR/$BZFILE checksum ..."
  [[ -f "/$BZDIR/$BZFILE" ]] || abort "$BZFILE not present"
  local BZFILECHK="$BZFILE.sha256"
  [[ -f "/$BZDIR/$BZFILECHK" ]] || abort "$BZFILECHK not present"
  local HASH1=$(/bin/sha256sum /$BZDIR/$BZFILE)
  local HASH2=$(/bin/cat /$BZDIR/$BZFILECHK)
  [[ ${HASH1:0:64} != ${HASH2:0:64} ]] && abort "$BZFILE checksum error"
}

bzmount(){
  local BZDIR="$1"
  local BZFILE="$2"
  local MNTDIR="$3"
  /sbin/mount -v -r -t squashfs /$BZDIR/$BZFILE /$MNTDIR || abort "cannot mount /$BZDIR/$BZFILE"
  # setup an overlayfs
  /bin/mkdir -p /var/local/overlay/$MNTDIR
  /bin/mkdir -p /var/local/overlay-work/$MNTDIR
  /sbin/mount -v -t overlay overlay -o lowerdir=/$MNTDIR,upperdir=/var/local/overlay/$MNTDIR,workdir=/var/local/overlay-work/$MNTDIR /$MNTDIR
}

# if unraidvers set, use that previous version
echo "booting $VERS"
[[ -z "$VERS" ]] && BRANCH="boot" || BRANCH="boot/vers/$VERS"

if [[ $ROOT == "" ]]; then
  # verify checksums
  bzcheck "$BRANCH" "bzimage"     	# kernel and /lib/modules
  bzcheck "$BRANCH" "bzroot"		# rootfs, sans /lib/modules, /lib/firmware, and /usr
  bzcheck "$BRANCH" "bzfirmware"	# /lib/firmware
  bzcheck "$BRANCH" "bzmodules"		# /usr
  bzcheck "$BRANCH" "bzroot-gui"	# /etc/inittab overrride

  # mount squashfs file systems
  if [[ -f /boot/config/fastusr ]]; then
    /bin/cp /$BRANCH/bzroot-gui /usr
    bzmount "usr" "bzmodules" "usr"
  else
    bzmount "$BRANCH" "bzmodules" "usr"
  fi
  bzmount "$BRANCH" "bzfirmware" "lib/firmware"

  # make /etc/rc.d a symlink
  /bin/rm -rf /etc/rc.d
  /bin/ln -sf /usr/local/etc/rc.d /etc

  # move /var/log to a tmpfs
  /bin/mv -f /var/log/* /var/empty
  /sbin/mount -t tmpfs -o size=128m,mode=0755 tmpfs /var/log
  /bin/mv -f /var/empty/* /var/log

else
  log "Checking root filesystem"
  /sbin/fsck -C -a $ROOT
  RETVAL=$?
  [[ $RETVAL -ge 2 ]] && abort "fsck failed with return value $RETVAL"
  # Remount the root filesystem in read-write mode
  log "Remounting $ROOT with read-write enabled."
  /sbin/mount -w -v -n -o remount /
  RETVAL=$?
  [[ $RETVAL -gt 0 ]] && abort "failed to remount $ROOT r/w with return value $RETVAL"
  [[ ! -f /etc/rc.d/rc.S.cont ]] && abort "unable to continue - you must remove 'root=$ROOT' from syslinux.cfg"
fi

# set permissions for non vfat boot on /boot
if [[ $FSTYPE != vfat ]]; then
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
