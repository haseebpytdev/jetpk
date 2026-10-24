#!/usr/bin/env bash
set -euo pipefail
CERT=/home/pkjetp/jetpk_secrets/zapways/jetpakistan-zapways.crt.pem
KEY=/home/pkjetp/jetpk_secrets/zapways/jetpakistan-zapways.key.pem
BASE=https://otatest4.zapways.com/v3.0/OTAAPI.asmx
OUT=/tmp/airblue-v3-wsdl-$(date -u +%Y%m%dT%H%M%SZ)
mkdir -p "$OUT"

OPS=(AirLowFareSearch AirBook AirSeatMap AirAncillaryItems AirDemandTicket AirBookModify Read Cancel)

echo "CERT_EXISTS=$([ -f "$CERT" ] && echo YES || echo NO)"
echo "KEY_EXISTS=$([ -f "$KEY" ] && echo YES || echo NO)"
if [ ! -f "$CERT" ] || [ ! -f "$KEY" ]; then
  echo "FATAL=CERT_OR_KEY_MISSING"
  exit 2
fi

WSDL_HTTP=$(curl -sS -o "$OUT/OTAAPI.wsdl" -w "%{http_code}" --cert "$CERT" --key "$KEY" --max-time 45 \
  "${BASE}?WSDL" || echo 000)
echo "V3_WSDL_HTTP=$WSDL_HTTP"
echo "WSDL_BYTES=$(wc -c < "$OUT/OTAAPI.wsdl" | tr -d ' ')"
if [ "$WSDL_HTTP" != "200" ]; then
  echo "FATAL=WSDL_FETCH_FAILED"
  exit 3
fi

for op in "${OPS[@]}"; do
  code=$(curl -sS -o "$OUT/op-${op}.html" -w "%{http_code}" --cert "$CERT" --key "$KEY" --max-time 30 \
    "${BASE}?op=${op}" || echo 000)
  echo "HELP_${op}_HTTP=$code"
done

python3 - <<'PY' "$OUT/OTAAPI.wsdl" "$OUT" "$OUT/parse.json"
import sys, re, json, xml.etree.ElementTree as ET
from pathlib import Path
wsdl_path, out_dir, out_json = sys.argv[1], Path(sys.argv[2]), sys.argv[3]
ops_wanted = ["AirLowFareSearch","AirBook","AirSeatMap","AirAncillaryItems","AirDemandTicket","AirBookModify","Read","Cancel"]
ns = {
    "wsdl": "http://schemas.xmlsoap.org/wsdl/",
    "soap": "http://schemas.xmlsoap.org/wsdl/soap/",
    "soap12": "http://schemas.xmlsoap.org/wsdl/soap12/",
}
tree = ET.parse(wsdl_path)
root = tree.getroot()
tns = root.get("targetNamespace")

wsdl_actions = {}
wsdl_versions = {}
for b in root.findall(".//wsdl:binding", ns):
    for op in b.findall("wsdl:operation", ns):
        name = op.get("name")
        if name not in ops_wanted:
            continue
        soap_op = op.find("soap:operation", ns)
        soap12_op = op.find("soap12:operation", ns)
        action = None
        ver = None
        if soap_op is not None and soap_op.get("soapAction"):
            action = soap_op.get("soapAction")
            ver = "1.1"
        elif soap12_op is not None and soap12_op.get("soapAction"):
            action = soap12_op.get("soapAction")
            ver = "1.2"
        if action and name not in wsdl_actions:
            wsdl_actions[name] = action
            wsdl_versions[name] = ver
        elif action and name in wsdl_actions and wsdl_versions.get(name) != "1.1" and ver == "1.1":
            wsdl_actions[name] = action
            wsdl_versions[name] = ver

services = []
for s in root.findall(".//wsdl:service", ns):
    for p in s.findall("wsdl:port", ns):
        addr = p.find("soap:address", ns)
        loc = addr.get("location") if addr is not None else None
        if loc:
            services.append({"service": s.get("name"), "port": p.get("name"), "location": loc, "binding": p.get("binding")})

help_actions = {}
for name in ops_wanted:
    help_path = out_dir / f"op-{name}.html"
    text = help_path.read_text(encoding="utf-8", errors="replace") if help_path.exists() else ""
    found = []
    for pat in [
        r"SOAPAction:\s*([^\s<\"']+)",
        r'soapAction\s*=\s*"([^"]+)"',
        r"soapAction\s*=\s*'([^']+)'",
        r"HTTP Header SOAPAction[:\s]+([^\s<\"]+)",
    ]:
        found += re.findall(pat, text, flags=re.I)
    for m in re.finditer(r"SOAPAction[^<]{0,40}(https?://[^<\"\s]+|http://[^<\"\s]+)", text, flags=re.I):
        found.append(m.group(1))
    uniq=[]; seen=set()
    for a in found:
        a=a.strip().rstrip('"').rstrip("'")
        if a and a not in seen:
            seen.add(a); uniq.append(a)
    help_actions[name] = uniq

matches = {}
all_match = True
for name in ops_wanted:
    w = wsdl_actions.get(name)
    h = help_actions.get(name) or []
    ok = bool(w) and bool(h) and (w in h)
    matches[name] = {"wsdl": w, "help": h, "match": ok, "soap_version": wsdl_versions.get(name)}
    if not ok:
        all_match = False

result = {
    "V3_WSDL_NAMESPACE": tns,
    "V3_WSDL_ENDPOINT": services[0]["location"] if services else None,
    "V3_WSDL_SERVICE": services[0]["service"] if services else None,
    "V3_WSDL_PORT": services[0]["port"] if services else None,
    "V3_SOAP_VERSION_PRIMARY": "1.1",
    "V3_METADATA_ACTIONS_MATCH": all_match,
    "operations": matches,
    "services": services,
}
Path(out_json).write_text(json.dumps(result, indent=2), encoding="utf-8")
print(f"V3_WSDL_NAMESPACE={tns}")
print(f"V3_WSDL_ENDPOINT={result['V3_WSDL_ENDPOINT']}")
print(f"V3_METADATA_ACTIONS_MATCH={'YES' if all_match else 'NO'}")
key_map = {
    "AirLowFareSearch":"V3_ACTION_SEARCH",
    "AirBook":"V3_ACTION_BOOK",
    "AirSeatMap":"V3_ACTION_SEAT_MAP",
    "AirAncillaryItems":"V3_ACTION_ANCILLARY",
    "AirDemandTicket":"V3_ACTION_TICKET",
    "AirBookModify":"V3_ACTION_MODIFY",
    "Read":"V3_ACTION_READ",
    "Cancel":"V3_ACTION_CANCEL",
}
for name, key in key_map.items():
    print(f"{key}={wsdl_actions.get(name) or 'ABSENT'}")
    m = matches[name]
    print(f"{key}_HELP={('|'.join(m['help']) if m['help'] else 'ABSENT')}")
    print(f"{key}_MATCH={'YES' if m['match'] else 'NO'}")
PY

echo "PARSE_JSON=$OUT/parse.json"
echo "OUT_DIR=$OUT"
echo "DONE=YES"
