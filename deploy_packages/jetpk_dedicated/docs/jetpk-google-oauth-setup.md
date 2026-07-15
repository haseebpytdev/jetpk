# JetPK Google OAuth — dedicated server setup

**Phase:** 7J  
**When:** After Laravel app is deployed and `.env` exists on dedicated server  
**Do not commit** `GOOGLE_CLIENT_SECRET` to git or deployment package.

---

## Prerequisites

- Dedicated server live at `https://jetpakistan.com`
- `.env` on server with `APP_URL=https://jetpakistan.com`
- Access to JetPK’s **official Google / email account** (not Master/Parwaaz account)

---

## Steps

1. Sign in to **Google Cloud Console** using JetPK’s official Google/email account.

2. **Create or select** a project for JetPakistan (e.g. `JetPakistan Web`).

3. Open **APIs & Services → OAuth consent screen**:
   - User type: External (or Internal if Workspace-only)
   - App name: `JetPakistan`
   - Support email: JetPK operations email
   - Authorized domains: `jetpakistan.com`
   - Save

4. Open **APIs & Services → Credentials → Create credentials → OAuth client ID**:
   - Application type: **Web application**
   - Name: `JetPakistan Production`

5. **Authorized JavaScript origins:**
   ```
   https://jetpakistan.com
   ```

6. **Authorized redirect URIs:**
   ```
   https://jetpakistan.com/auth/google/callback
   ```

7. Copy **Client ID** and **Client secret** into dedicated server `.env` only:
   ```env
   GOOGLE_CLIENT_ID=<paste client id>
   GOOGLE_CLIENT_SECRET=<paste client secret>
   GOOGLE_REDIRECT_URI=https://jetpakistan.com/auth/google/callback
   ```

8. On server, clear caches:
   ```bash
   cd /home/<jetpk_user>/domains/jetpakistan.com/ota_app
   php artisan optimize:clear
   php artisan cache:clear
   ```

9. **Test:** Open `https://jetpakistan.com/login` → **Continue with Google** → confirm redirect returns to JetPK home/dashboard without error.

---

## Notes

- Mode A preview URL `/jetpk/auth/google/redirect` is for shared Master server only — not used on dedicated root mode.
- If OAuth was previously registered for another domain, add the JetPakistan callback as an additional URI or create a separate OAuth client for production.
- Never store the client secret in `deploy_packages/`, manifests, or git.

---

## Rollback

Remove or comment `GOOGLE_*` lines in `.env`, run `php artisan config:clear`. Email/password login remains available.
