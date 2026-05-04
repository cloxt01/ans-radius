#!/bin/bash

WG_IF="wg0"

wg show $WG_IF dump | while read line; do
    PUBKEY=$(echo $line | awk '{print $1}')
    LAST_HANDSHAKE=$(echo $line | awk '{print $5}')

    # skip header/interface
    if [[ "$PUBKEY" == "private_key" ]]; then
        continue
    fi

    # kalau handshake = 0 → gak pernah connect
    if [[ "$LAST_HANDSHAKE" == "0" ]]; then
        echo "Removing unused peer: $PUBKEY"
        wg set $WG_IF peer $PUBKEY remove
    fi
done