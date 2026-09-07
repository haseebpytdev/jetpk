from pathlib import Path

auth = Path(r"C:\Users\khadi\ota-jetpk\tests\Feature\Auth\AuthLoginSecurityEmailCanonicalTest.php")
t = auth.read_text(encoding="utf-8")
t2 = t.replace(
    "$this->assertStringContainsString($mail->plainBody, $mail->render('text'));",
    "$this->assertSame('emails.themes.jetpakistan.plain-text', $mail->content()->text);\n"
    "            $this->assertSame($mail->plainBody, $mail->content()->with['plainBody'] ?? null);",
)
if t2 == t:
    raise SystemExit("auth replace failed")
auth.write_text(t2, encoding="utf-8")
print("auth_ok")

layout = Path(r"C:\Users\khadi\ota-jetpk\tests\Feature\Communication\OtaOperationalNotificationModernLayoutTest.php")
u = layout.read_text(encoding="utf-8")
u2 = u.replace(
    "$this->assertStringContainsString($plainBody, $mailable->render('text'));",
    "$this->assertSame('emails.themes.jetpakistan.plain-text', $mailable->content()->text);\n"
    "        $this->assertSame($plainBody, $mailable->content()->with['plainBody'] ?? null);",
)
if u2 == u:
    raise SystemExit("layout replace failed")
layout.write_text(u2, encoding="utf-8")
print("layout_ok")
