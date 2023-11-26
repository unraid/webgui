#!/usr/bin/env bash
# -*- coding: utf-8 -*-
#
# =============================================================================
#
# The MIT License (MIT)
#
# Copyright (c) 2015-2021 Andre Richter
#
# Permission is hereby granted, free of charge, to any person obtaining a copy
# of this software and associated documentation files (the "Software"), to deal
# in the Software without restriction, including without limitation the rights
# to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
# copies of the Software, and to permit persons to whom the Software is
# furnished to do so, subject to the following conditions:
#
# The above copyright notice and this permission notice shall be included in all
# copies or substantial portions of the Software.
#
# THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
# IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
# FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
# AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
# LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
# OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
# SOFTWARE.
#
# =============================================================================
#
# Author(s):
#   Andre Richter, <andre.o.richter @t gmail_com>
#
# =============================================================================
#
# This script takes one or two parameters in any order:
#   <Vendor:Device> i.e. vvvv:dddd
#   <Domain:Bus:Device.Function> i.e. dddd:bb:dd.f
# and then:
#
#  (1) If both <Vendor:Device> and <Domain:Bus:Device.Function> were provided,
#      validate that the requested <Vendor:Device> exists at <Domain:Bus:Device.Function>
#
#      If only <Vendor:Device> was provided, determine the current 
#      <Domain:Bus:Device.Function> for that device.
#
#      If only <Domain:Bus:Device.Function> was provided, use it.
#
#  (2) Unbinds all devices that are in the same iommu group as the supplied
#      device from their current driver (except PCIe bridges).
#
#  (3) Binds to vfio-pci:
#    (3.1) The supplied device.
#    (3.2) All devices that are in the same iommu group.
#
#  (4) Transfers ownership of the respective iommu group inside /dev/vfio
#      to $SUDO_USER
#
# Script must be executed via sudo

BDF_REGEX="^[[:xdigit:]]{2}:[[:xdigit:]]{2}.[[:xdigit:]]$"
DBDF_REGEX="^[[:xdigit:]]{4}:[[:xdigit:]]{2}:[[:xdigit:]]{2}.[[:xdigit:]]$"
VD_REGEX="^[[:xdigit:]]{4}:[[:xdigit:]]{4}$"

if [[ $EUID -ne 0 ]]; then
    echo "Error: This script must be run as root" 1>&2
    exit 1
fi

if [[ -z "$@" ]]; then
    echo "Error: Please provide Domain:Bus:Device.Function (dddd:bb:dd.f) and/or Vendor:Device (vvvv:dddd)" 1>&2
    exit 1
fi

unset VD BDF
for arg in "$@"
do
    if [[ $arg =~ $VD_REGEX ]]; then
        VD=$arg
    elif [[ $arg =~ $DBDF_REGEX ]]; then
        BDF=$arg
    elif [[ $arg =~ $BDF_REGEX ]]; then
        BDF="0000:${arg}"
        echo "Warning: You did not supply a PCI domain, assuming ${BDF}" 1>&2
    else
        echo "Error: Please provide Vendor:Device (vvvv:dddd) and/or Domain:Bus:Device.Function (dddd:bb:dd.f)" 1>&2
        exit 1
    fi
done

# BDF not provided, find BDF for Vendor:Device
if [[ -z $BDF ]]; then
    COUNT=$(lspci -n -d ${VD} 2>/dev/null | wc -l)
    if [[ $COUNT -eq 0 ]]; then
        echo "Error: Vendor:Device ${VD} not found" 1>&2
        exit 1
    elif [[ $COUNT -gt 1 ]]; then
        echo "Error: Multiple results for Vendor:Device ${VD}, please provide Domain:Bus:Device.Function (dddd:bb:dd.f) as well" 1>&2
        exit 1
    fi
    BDF=$(lspci -n -d ${VD} 2>/dev/null | cut -d " " -f1)
    if [[ $BDF =~ $BDF_REGEX ]]; then
        BDF="0000:${BDF}"
    elif [[ ! $BDF =~ $DBDF_REGEX ]]; then
        echo "Error: Unable to find Domain:Bus:Device.Function for Vendor:Device ${VD}" 1>&2
        exit 1
    fi
fi

TARGET_DEV_SYSFS_PATH="/sys/bus/pci/devices/$BDF"

if [[ ! -d $TARGET_DEV_SYSFS_PATH ]]; then
    echo "Error: Device ${BDF} does not exist, unable to bind device" 1>&2
    exit 1
fi

if [[ ! -d "$TARGET_DEV_SYSFS_PATH/iommu/" ]]; then
    echo "Error: No signs of an IOMMU. Check your hardware and/or linux cmdline parameters. Use intel_iommu=on or iommu=pt iommu=1" 1>&2
    exit 1
fi

# validate that the correct Vendor:Device was found for this BDF
if [[ ! -z $VD ]]; then
    if [[ $(lspci -n -s ${BDF} -d ${VD} 2>/dev/null | wc -l) -eq 0 ]]; then
        echo "Error: Vendor:Device ${VD} not found at ${BDF}, unable to bind device" 1>&2
        exit 1
    else
        echo "Vendor:Device ${VD} found at ${BDF}"
    fi
else
    echo "Warning: You did not specify a Vendor:Device (vvvv:dddd), unable to validate ${BDF}" 1>&2
fi

unset dev_sysfs_paths
for dsp in $TARGET_DEV_SYSFS_PATH/iommu_group/devices/*
do
    dbdf=${dsp##*/}
    if [[ $(( 0x$(setpci -s $dbdf 0e.b) & 0x7f )) -eq 0 ]]; then
        dev_sysfs_paths+=( $dsp )
    fi
done

printf "\nIOMMU group members (sans bridges):\n"
for dsp in ${dev_sysfs_paths[@]}; do echo $dsp; done

modprobe -i vfio-pci
if [[ $? -ne 0 ]]; then
    echo "Error: Error probing vfio-pci" 1>&2
    exit 1
fi

printf "\nBinding...\n"
for dsp in ${dev_sysfs_paths[@]}
do
    dpath="$dsp/driver"
    dbdf=${dsp##*/}

    echo "vfio-pci" > "$dsp/driver_override"

    if [[ -d $dpath ]]; then
        curr_driver=$(readlink $dpath)
        curr_driver=${curr_driver##*/}

        if [[ "$curr_driver" == "vfio-pci" ]]; then
            echo "$dbdf already bound to vfio-pci"
            continue
        else
            echo $dbdf > "$dpath/unbind"
            echo "Unbound $dbdf from $curr_driver"
        fi
    fi

    echo $dbdf > /sys/bus/pci/drivers_probe
done

printf "\n"

# limetech - /dev/vfio not available when called via rc.S
## Adjust group ownership
#iommu_group=$(readlink $TARGET_DEV_SYSFS_PATH/iommu_group)
#iommu_group=${iommu_group##*/}
#chown $SUDO_UID:$SUDO_GID "/dev/vfio/$iommu_group"
#if [[ $? -ne 0 ]]; then
#    echo "Error: unable to adjust group ownership of /dev/vfio/${iommu_group}" 1>&2
#    exit 1
#fi

printf "success...\n\n"
echo "Device ${VD} at ${BDF} bound to vfio-pci"

# limetech - moved to wrapper script
#echo 'Devices listed in /sys/bus/pci/drivers/vfio-pci:'
#ls -l /sys/bus/pci/drivers/vfio-pci | egrep [[:xdigit:]]{4}:
#printf "\nls -l /dev/vfio/\n"
#ls -l /dev/vfio/
