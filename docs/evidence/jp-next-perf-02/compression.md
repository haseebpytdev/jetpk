# Compression

Browsers receive compressed JS/CSS (`encodedBodySize` < `decodedBodySize`).  
`curl -I` without content negotiation may omit `content-encoding`; browser evidence is authoritative.  
NEXT_JS_COMPRESSION=PASS
