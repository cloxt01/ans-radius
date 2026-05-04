#!/bin/bash

WG_IF="" # nama interface mis (wg0)
SUBNET="10.7.0" # subnet mis (10.7.0)
DNS="8.8.8.8" # dns
ENDPOINT="x.x.x.x" # endpoint / ip server
INTERFACE_PORT= 16315 # interface port mis (16315)

TYPE=${1:-1}  # default type 1

LOCK_FILE="/tmp/wg.lock"

# =========================
# LOCK (biar gak tabrakan)
# =========================
exec 200>$LOCK_FILE
flock -x 200

# =========================
# INFO SERVER
# =========================
SERVER_PUB=$(wg show $WG_IF public-key)
LISTEN_PORT=$(wg show $WG_IF listen-port)

# =========================
# AMBIL IP TERPAKAI
# =========================
USED_IPS=$(wg show $WG_IF allowed-ips | grep -oE "$SUBNET\.[0-9]+" | sort -u)

# =========================
# CARI IP KOSONG
# =========================
FREE_IP=""
for i in $(seq 2 254); do
    IP="$SUBNET.$i"
    if ! echo "$USED_IPS" | grep -q "$IP"; then
        FREE_IP=$IP
        break
    fi
done

if [ -z "$FREE_IP" ]; then
    echo "No free IP available"
    exit 1
fi

# =========================
# GENERATE KEY CLIENT
# =========================
PRIVATE_KEY=$(wg genkey)
PUBLIC_KEY=$(echo "$PRIVATE_KEY" | wg pubkey)

# =========================
# REGISTER KE SERVER
# =========================
wg set $WG_IF peer "$PUBLIC_KEY" allowed-ips "$FREE_IP/32"
wg-quick save $WG_IF

# =========================
# OUTPUT SESUAI TYPE
# =========================

# ===== TYPE 1: CLIENT CONFIG =====
if [ "$TYPE" = "1" ]; then

cat <<EOF
[Interface]
Address = $FREE_IP/24
DNS = $DNS
PrivateKey = $PRIVATE_KEY

[Peer]
PublicKey = $SERVER_PUB
Endpoint = $ENDPOINT:$LISTEN_PORT
AllowedIPs = 0.0.0.0/0
PersistentKeepalive = 25
EOF

# ===== TYPE 2: MIKROTIK SCRIPT =====
elif [ "$TYPE" = "2" ]; then

cat <<EOF
# Remove lama
/interface wireguard remove [find name=wg1]

# Tambah interface
/interface wireguard add name=wg1 listen-port=$INTERFACE_PORT private-key="$PRIVATE_KEY"

# Assign IP
/ip address add address=$FREE_IP/24 interface=wg1

# Peer (server)
/interface wireguard peers add \\
interface=wg1 \\
public-key="$SERVER_PUB" \\
endpoint-address=$ENDPOINT \\
endpoint-port=$LISTEN_PORT \\
allowed-address=0.0.0.0/0 \\
persistent-keepalive=25

# Route
/ip route add dst-address=10.7.0.0/24 gateway=wg1

EOF

else
    echo "Invalid type. Use 1 or 2"
fi
