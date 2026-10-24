#!/usr/bin/env bash
set -euo pipefail
CERT=/home/pkjetp/jetpk_secrets/zapways/jetpakistan-zapways.crt.pem
KEY=/home/pkjetp/jetpk_secrets/zapways/jetpakistan-zapways.key.pem
OUT=/tmp/airblue-wsdl-read-$(date -u +%Y%m%dT%H%M%SZ)
mkdir -p "$OUT"

echo "CERT_EXISTS=$([ -f "$CERT" ] && echo YES || echo NO)"
echo "KEY_EXISTS=$([ -f "$KEY" ] && echo YES || echo NO)"
if [ ! -f "$CERT" ] || [ ! -f "$KEY" ]; then
  echo "FATAL=CERT_OR_KEY_MISSING"
  exit 2
fi

WSDL_HTTP=$(curl -sS -o "$OUT/OTAAPI.wsdl" -w "%{http_code}" --cert "$CERT" --key "$KEY" --max-time 30 \
  "https://otatest4.zapways.com/v2.0/OTAAPI.asmx?WSDL" || echo 000)
echo "WSDL_FETCH_HTTP=$WSDL_HTTP"
echo "WSDL_BYTES=$(wc -c < "$OUT/OTAAPI.wsdl" | tr -d ' ')"

HELP_HTTP=$(curl -sS -o "$OUT/op-Read.html" -w "%{http_code}" --cert "$CERT" --key "$KEY" --max-time 30 \
  "https://otatest4.zapways.com/v2.0/OTAAPI.asmx?op=Read" || echo 000)
echo "HELP_PAGE_FETCH_HTTP=$HELP_HTTP"
echo "HELP_BYTES=$(wc -c < "$OUT/op-Read.html" | tr -d ' ')"
echo "OUT_DIR=$OUT"

if [ "$WSDL_HTTP" != "200" ]; then
  echo "WSDL_FETCH_PASS=NO"
  exit 3
fi
echo "WSDL_FETCH_PASS=YES"

# Parse Read soapAction from WSDL (SOAP 1.1 soap:operation)
python3 - <<'PY' "$OUT/OTAAPI.wsdl" "$OUT/op-Read.html" "$OUT/parse.json"
import sys, re, json, xml.etree.ElementTree as ET
wsdl_path, help_path, out_json = sys.argv[1], sys.argv[2], sys.argv[3]
ns = {
    'wsdl': 'http://schemas.xmlsoap.org/wsdl/',
    'soap': 'http://schemas.xmlsoap.org/wsdl/soap/',
    'soap12': 'http://schemas.xmlsoap.org/wsdl/soap12/',
}
tree = ET.parse(wsdl_path)
root = tree.getroot()

read_ops = []
for op in root.findall('.//wsdl:binding/wsdl:operation', ns):
    name = op.get('name')
    if name != 'Read':
        continue
    binding = op.getparent() if hasattr(op, 'getparent') else None
    # ElementTree has no getparent; find binding by scanning
    soap_op = op.find('soap:operation', ns)
    soap12_op = op.find('soap12:operation', ns)
    action = None
    soap_ver = None
    if soap_op is not None:
        action = soap_op.get('soapAction')
        soap_ver = '1.1'
    elif soap12_op is not None:
        action = soap12_op.get('soapAction')
        soap_ver = '1.2'
    read_ops.append({'action': action, 'soap_version': soap_ver})

# binding/service/port/location
bindings = []
for b in root.findall('.//wsdl:binding', ns):
    bname = b.get('name')
    style = None
    transport = None
    soap_bind = b.find('soap:binding', ns)
    if soap_bind is not None:
        style = soap_bind.get('style')
        transport = soap_bind.get('transport')
    bindings.append({'name': bname, 'style': style, 'transport': transport})

services = []
for s in root.findall('.//wsdl:service', ns):
    sname = s.get('name')
    for p in s.findall('wsdl:port', ns):
        pname = p.get('name')
        addr = p.find('soap:address', ns)
        loc = addr.get('location') if addr is not None else None
        services.append({'service': sname, 'port': pname, 'location': loc, 'binding': p.get('binding')})

# Help page SOAPAction extraction
help_text = open(help_path, encoding='utf-8', errors='replace').read()
help_actions = []
for pat in [
    r'SOAPAction:\s*([^\s<"\']+)',
    r'soapAction\s*=\s*"([^"]+)"',
    r"soapAction\s*=\s*'([^']+)'",
    r'HTTP Header SOAPAction[:\s]+([^\s<"]+)',
]:
    help_actions += re.findall(pat, help_text, flags=re.I)

# Also look near "SOAPAction" labels in HTML
for m in re.finditer(r'SOAPAction[^<]{0,40}(https?://[^<"\s]+|http://[^<"\s]+)', help_text, flags=re.I):
    help_actions.append(m.group(1))

# unique preserve order
seen=set(); help_unique=[]
for a in help_actions:
    a=a.strip().rstrip('"').rstrip("'")
    if a and a not in seen:
        seen.add(a); help_unique.append(a)

# Prefer SOAP 1.1 Read action
wsdl_action = None
wsdl_ver = None
for op in read_ops:
    if op['action']:
        wsdl_action = op['action']
        wsdl_ver = op['soap_version']
        break

# Find binding that contains Read
binding_name = None
for b in root.findall('.//wsdl:binding', ns):
    for op in b.findall('wsdl:operation', ns):
        if op.get('name') == 'Read':
            binding_name = b.get('name')
            break

result = {
    'WSDL_READ_OPERATION_FOUND': bool(read_ops and wsdl_action),
    'WSDL_READ_SOAP_ACTION': wsdl_action,
    'WSDL_SOAP_VERSION': wsdl_ver,
    'WSDL_BINDING_NAME': binding_name,
    'WSDL_SERVICE_ENDPOINT': services[0]['location'] if services else None,
    'WSDL_SERVICE_NAME': services[0]['service'] if services else None,
    'WSDL_PORT_NAME': services[0]['port'] if services else None,
    'WSDL_BINDINGS': bindings,
    'WSDL_SERVICES': services,
    'HELP_PAGE_READ_SOAP_ACTIONS': help_unique,
    'READ_OPS_RAW': read_ops,
}
open(out_json,'w',encoding='utf-8').write(json.dumps(result, indent=2))
for k,v in result.items():
    if k in ('WSDL_BINDINGS','WSDL_SERVICES','READ_OPS_RAW','HELP_PAGE_READ_SOAP_ACTIONS'):
        continue
    print(f'{k}={v}')
print('HELP_PAGE_READ_SOAP_ACTION=' + (help_unique[0] if help_unique else 'ABSENT'))
print('HELP_PAGE_ACTION_COUNT=' + str(len(help_unique)))
if len(help_unique) > 1:
    print('HELP_PAGE_ALL_ACTIONS=' + '|'.join(help_unique[:5]))
PY

echo "PARSE_JSON=$OUT/parse.json"
echo "DONE=YES"
