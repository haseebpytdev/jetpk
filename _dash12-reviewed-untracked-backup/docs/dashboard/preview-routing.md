# Preview Routing

## DASH-01 (current)

- Next.js runs **independently** on port **3001**
- `basePath: /testdash` in [`dashboard/next.config.ts`](../../dashboard/next.config.ts)
- **No** Laravel routes, proxy, or `public/testdash` sync
- **No** changes to `/admin` or `/dashboard`

## Future same-origin mount (documented only)

Hostinger deployment uses Apache with Laravel `public/.htaccess` (existing files served before `index.php`).

Recommended later approach:

1. `next build` with static export configuration when module pages exist
2. Copy output to `public/testdash/` on the Laravel app
3. Add `testdash` to `ReservedClientPreviewSlugs` so client parity routes cannot capture the path
4. **Avoid** Laravel HTTP proxy to a Node process on shared hosting

Reverse-proxy via nginx/Forge is **not** present in this repository and is out of scope for JetPK SFTP workflow.

## Security

Preview builds must keep `NEXT_PUBLIC_ALLOW_MUTATIONS=false` until authenticated API integration is complete.
