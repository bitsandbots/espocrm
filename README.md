# EspoCRM — NEXUS Integration Fork

EspoCRM instance with the [NEXUS](https://github.com/bitsandbots/nexus) agentic platform module.

## What's here

This is a working EspoCRM installation plus the `Nexus` custom module under `application/Espo/Modules/Nexus/` and `client/modules/nexus/`. It connects EspoCRM to a running NEXUS gateway for:

- **Agent chat** — inline AI assistant on Account, Contact, Lead, and Case records
- **Job queue** — fire-and-forget prompts routed through NEXUS (`/api/v1/nexus/submit`)
- **RAG ingestion** — entity saves push a text summary into NEXUS's knowledge base so the agent has CRM context
- **Admin settings panel** — configure connection URL, credentials, and feature flags from EspoCRM Admin

## Requirements

- EspoCRM 9.x
- PHP 8.3+
- A running [NEXUS gateway](https://github.com/bitsandbots/nexus) instance (default: `http://potpie.local:5000`)

## Installation

The module lives in `application/Espo/Modules/Nexus/` and `client/modules/nexus/` within this repo. After cloning and setting up EspoCRM normally:

```bash
php command.php rebuild
```

## Configuration

In EspoCRM: **Admin → NEXUS Integration**

| Setting | Default | Description |
|---|---|---|
| `nexusUrl` | `http://potpie.local:5000` | NEXUS gateway base URL |
| `nexusUsername` | — | NEXUS API username |
| `nexusPassword` | — | NEXUS API password |
| `nexusEnabled` | false | Enable gateway connectivity |
| `nexusRagEnabled` | true | Push entity saves into RAG knowledge base |

## API Routes (added by module)

| Method | Path | Description |
|---|---|---|
| GET | `/api/v1/nexus/health` | Ping NEXUS gateway |
| GET | `/api/v1/nexus/settings` | Read current config |
| PUT | `/api/v1/nexus/settings` | Save config |
| POST | `/api/v1/nexus/chat` | Synchronous agent chat |
| POST | `/api/v1/nexus/submit` | Submit async job to queue |
| GET | `/api/v1/nexus/status/:jobId` | Poll job status |
| GET | `/api/v1/nexus/result/:jobId` | Fetch completed job result |

## Extracted standalone modules

The following integrations have been extracted to their own repos:

- [espocrm-xero](https://github.com/bitsandbots/espocrm-xero) — Bidirectional Xero sync
- [espocrm-quickbooks](https://github.com/bitsandbots/espocrm-quickbooks) — QuickBooks Online sync
- [espocrm-inventory](https://github.com/bitsandbots/espocrm-inventory) — Inventory tracking

## License

EspoCRM is licensed under [GNU AGPLv3](LICENSE.txt). The NEXUS module is MIT.
