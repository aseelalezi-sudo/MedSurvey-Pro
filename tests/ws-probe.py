"""One synthetic Pusher handshake via the local fixture Nginx. No third-party modules."""
import base64
import json
import os
import socket
import struct
import sys

origin, expected = sys.argv[1:]
with socket.create_connection(("127.0.0.1", 18080), timeout=5) as sock:
    key = base64.b64encode(os.urandom(16)).decode()
    request = (
        "GET /app/fixture?protocol=7&client=js&version=8 HTTP/1.1\r\n"
        "Host: localhost\r\nUpgrade: websocket\r\nConnection: Upgrade\r\n"
        f"Origin: https://{origin}\r\nSec-WebSocket-Key: {key}\r\n"
        "Sec-WebSocket-Version: 13\r\n\r\n"
    )
    sock.sendall(request.encode())
    stream = sock.makefile("rb")
    assert stream.readline().startswith(b"HTTP/1.1 101 ")
    while stream.readline() not in (b"\r\n", b""):
        pass
    opcode, length = stream.read(2)
    assert opcode & 15 == 1
    if length == 126:
        length = struct.unpack("!H", stream.read(2))[0]
    message = json.loads(stream.read(length))
    assert message["event"] == {
        "established": "pusher:connection_established",
        "refused": "pusher:error",
    }[expected]
