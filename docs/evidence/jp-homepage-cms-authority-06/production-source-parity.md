# Production Source Parity — CustomerRegistrationForm timer fix

## Context

During deploy of engineering SHA `563d6d075258a28c5c00e441dc217b35ec574ad9`, the public Next build failed because `window.setTimeout` / `window.clearTimeout` were narrowed incorrectly in TypeScript.

Production was repaired with:

```typescript
const timer = globalThis.setTimeout(load, 150);
return () => globalThis.clearTimeout(timer);
```

Plus SSR guard:

```typescript
if (typeof window === "undefined") {
  return;
}
```

## Verification matrix

| Source | setTimeout | clearTimeout | SSR guard |
|--------|------------|--------------|-----------|
| Production `/home/pkjetp/jetpk_app/frontend/.../CustomerRegistrationForm.tsx` | `globalThis` | `globalThis` | YES |
| Remote Git @ `1a580b1d` | `window` | `window` | NO |
| Parity commit `a48f6e15` | `globalThis` | `globalThis` | YES |

## Parity commit

```
fix(auth): persist production registration timer build fix
SHA: a48f6e1591c09c2e0a886be58e76f7fc57b8ba7f
Branch: phase/jp-master-unfinished-closure-10
Pushed: jetpk remote
Redeploy for parity only: NOT REQUIRED (production already contains fix)
```

## Gates

| Gate | Result |
|------|--------|
| PRODUCTION_TS_HOTFIX_IDENTIFIED | YES |
| HOTFIX_SEMANTIC_DIFF | minimal |
| PRODUCTION_BEHAVIOR_PRESERVED | YES |
| PRODUCTION_TS_HOTFIX_IN_GIT | YES |
| PRODUCTION_SOURCE_PARITY | PASS (for this file) |
