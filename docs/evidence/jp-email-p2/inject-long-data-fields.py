from pathlib import Path
import re

p = Path(r"C:\Users\khadi\ota-jetpk\docs\evidence\jp-email-p2\artifacts\booking-long-data-stress.html")
html = p.read_text(encoding="utf-8")
if "PKR 987,654,321.99" in html and "longstress@example.test" in html:
    print("already_injected")
    raise SystemExit(0)

marker = "Booking status"
idx = html.find(marker)
if idx < 0:
    raise SystemExit("marker_missing")

# Find the start of the table row containing Booking status
row_start = html.rfind("<tr", 0, idx)
inject = """
        <tr>
          <td style="padding:8px 0 0 0;">
            <div style="font-size:12px; line-height:16px; color:#64748b;">Customer email</div>
            <div class="jetpk-long" style="font-size:15px; line-height:22px; color:#0f2435; font-weight:bold; word-break:break-word; overflow-wrap:break-word;">alexandria.maximiliana.catherine.therese.von.hohenzollern.sigmaringen.passenger.longstress@example.test</div>
          </td>
        </tr>
        <tr>
          <td style="padding:8px 0 0 0;">
            <div style="font-size:12px; line-height:16px; color:#64748b;">Amount due</div>
            <div class="jetpk-long" style="font-size:15px; line-height:22px; color:#0f2435; font-weight:bold; word-break:break-word; overflow-wrap:break-word;">PKR 987,654,321.99</div>
          </td>
        </tr>
"""
html = html[:row_start] + inject + html[row_start:]
# Prefer production CTA host for synthetic visual evidence
html = html.replace("http://jetpk.test", "https://jetpakistan.pk")
p.write_text(html, encoding="utf-8")
print("injected_ok")
