import hashlib
import os
import re
import subprocess

os.makedirs(r"docs/evidence/jp-airblue-zapways-wsdl-read-ticketing/_stage", exist_ok=True)
sha = "2ade5d0c3e77917d95210967d109a1b01e85ab15"
d = subprocess.check_output(["git", "show", f"{sha}:config/suppliers.php"])
path = r"docs/evidence/jp-airblue-zapways-wsdl-read-ticketing/_stage/suppliers.php"
open(path, "wb").write(d)
print(hashlib.sha256(d).hexdigest(), len(d))
text = d.decode("utf-8", errors="replace")
# Find v2 protocol block soap_action_test for read
m = re.search(
    r"'2\.0'\s*=>\s*\[.*?['\"]read['\"]\s*=>\s*\[[^\]]*?soap_action_test'\s*=>\s*'([^']+)'",
    text,
    re.S,
)
print("V2_TEST_ACTION", m.group(1) if m else "ABSENT")
