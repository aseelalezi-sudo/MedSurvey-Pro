import os

file_path = "src/components/ReportsPage.tsx"

with open(file_path, "r", encoding="utf-8") as f:
    content = f.read()

# We only want to decode the Arabic mojibake.
# The issue: UTF-8 bytes were decoded as cp1256, then saved as UTF-8.
# So we need to encode the string back to cp1256 to get the original UTF-8 bytes,
# then decode those bytes as UTF-8.

def fix_mojibake(match):
    try:
        # Encode the mojibake string as cp1256 to get the raw bytes
        raw_bytes = match.group(0).encode('cp1256')
        # Decode the raw bytes as UTF-8 to get the real Arabic text
        return raw_bytes.decode('utf-8')
    except Exception as e:
        return match.group(0)

import re
# Match runs of characters that appear in the mojibake.
# Mojibake from cp1256 reading UTF-8 typically includes Arabic letters like ط, ظ, and symbols like …, ً, ¤, etc.
# But we can just try to encode/decode the whole file? 
# No, encoding English text to cp1256 and decoding as UTF-8 might crash if it doesn't form valid UTF-8.
# Let's write a safe function that looks for valid UTF-8 sequences when encoded to cp1256.

def safe_fix_entire_content(text):
    # Instead of regex, let's just find sequences of characters that are valid when transformed.
    # We can just iterate word by word or chunk by chunk. 
    # Actually, the English letters encode to cp1256 identically to ASCII, and decode as ASCII in UTF-8.
    # The only problem is characters outside ASCII.
    result = []
    i = 0
    while i < len(text):
        if ord(text[i]) > 127:
            # try to find the longest sequence of non-ascii characters
            start = i
            while i < len(text) and ord(text[i]) > 127:
                i += 1
            chunk = text[start:i]
            try:
                fixed = chunk.encode('cp1256').decode('utf-8')
                result.append(fixed)
            except:
                # If it fails, just keep the original (though maybe we need to adjust boundaries)
                # UTF-8 sequences might contain ASCII occasionally? No, UTF-8 non-ASCII bytes are all > 127.
                # So any non-ASCII UTF-8 byte will map to a cp1256 character > 127.
                # Wait, cp1256 has some characters that are ASCII? No, 128-255.
                # Wait, cp1256 0x85 is '…' which is > 127.
                try:
                    # Let's try character by character? No, UTF-8 needs 2 bytes.
                    fixed = chunk.encode('cp1256').decode('utf-8')
                    result.append(fixed)
                except:
                    result.append(chunk)
        else:
            result.append(text[i])
            i += 1
    return "".join(result)

# Wait, `ord(text[i]) > 127` might fail if there's a space inside the Arabic string.
# A space in UTF-8 is 0x20. In cp1256 it's 0x20.
# So `text.encode('cp1256').decode('utf-8')` will actually work perfectly for the ENTIRE FILE!
# Because English letters (ASCII) encode to the exact same bytes in cp1256 and decode exactly the same in UTF-8.
# Let's test if the whole file can be transformed.

try:
    new_content = content.encode('cp1256').decode('utf-8')
    with open(file_path, "w", encoding="utf-8") as f:
        f.write(new_content)
    print("Successfully fixed entire file!")
except Exception as e:
    print("Failed to fix entire file at once:", e)
    # Fallback to line by line
    new_lines = []
    for line in content.splitlines():
        try:
            new_lines.append(line.encode('cp1256').decode('utf-8'))
        except:
            new_lines.append(line)
    with open(file_path, "w", encoding="utf-8") as f:
        f.write("\n".join(new_lines))
    print("Fixed line by line.")
