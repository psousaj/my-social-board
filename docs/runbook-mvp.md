# MVP Runbook

## Scope

This runbook covers ingestion, embeds runtime, developer clients, API consumer OAuth, v1 API, billing quotas/rate limits, privacy endpoints, and telemetry basics.

## Key flows

1. Ingestion run
- Endpoint: POST /{provider}/ingestion/run
- Requires tenant_id and provider_account_id
- Enforces monthly media ingestion quota
- Creates ingestion job with retry state

2. Embed runtime
- Token endpoint: POST /embeds/{embedId}/token
- Runtime endpoint: GET /embed/{embedId}?token=...
- Requires matching Origin header
- Security headers are always returned on success

3. API consumers
- Create client: POST /developer-clients
- Rotate secret with overlap: POST /developer-clients/{clientId}/rotate-secret
- OAuth token issue: POST /oauth/token
- Refresh: POST /oauth/token/refresh
- Revoke: POST /oauth/token/revoke

4. v1 API
- Requires Bearer token and X-Tenant-Id
- Endpoints: /v1/media, /v1/media/{itemId}, /v1/metrics, /v1/usage
- App-side rate limit enforced per minute

5. Privacy
- Export: POST /privacy/data-export
- Deauthorize: POST /privacy/deauthorize
- Deletion: POST /privacy/data-deletion

## Telemetry

- Every response includes X-Trace-Id
- Critical paths write to audit_events
- Error responses include error_code taxonomy

## Incident checks

1. Trace correlation
- Use X-Trace-Id from response headers
- Query audit_events by trace_id and tenant_id

2. Stuck ingestion jobs
- GET /{provider}/ingestion/jobs?tenant_id=...
- Check terminal error_code and attempts

3. OAuth client failures
- Validate client status and revoked_at
- Check secondary secret overlap window on rotation

4. Quota/rate issues
- Check tenant active subscription and quotas
- Verify minute-level rate counter behavior
