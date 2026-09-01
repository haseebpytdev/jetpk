# Here-document warning reconciliation

## HEREDOC_WARNING_SOURCE

`local_nested_shell_evidence_writer_02A_R2_and_local_powershell_ssh_quoting`

Observed warning pattern: `here-document ... delimited by end-of-file (wanted EOF)`.

Root cause class:

1. **Local** nested bash/PowerShell when packaging evidence samples / embedding remote snippets (02A-R2 evidence packaging).
2. **Local** PowerShell expansion of `$()` / nested quotes when invoking SSH from Windows (this session also produced a local `unexpected EOF while looking for matching '"'` on a malformed one-liner). That failure occurred **before** the remote production runner executed a truncated script body.

## Production impact proof

| Gate | Result |
|------|--------|
| Deploy / activation scripts closed heredocs (`<<'PY' … PY`) in `tmp/jetpk-jp-ai-assist-02b-activate2.sh` and `post-killswitch.sh` | Intact |
| `jetpk-production-run` activate2 / post-killswitch / health / final-check | `JETPK_OPERATION_RC=0` |
| Migrations truncated? | No migration in 02B |
| Cleanup truncated? | No |
| Evidence suite JSON produced with PASS flight/group gates | Yes (`suite.json`) |
| Final mode `public` + health ok | Yes |

## Return

HEREDOC_WARNING_PRODUCTION_IMPACT=**NO**
