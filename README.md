# Zaborprofil Platform

Production-grade B2B/B2C platform replacing zaborprofil.ru. Built with Next.js, Symfony, and Payload CMS.

## Architecture

- **Next.js** (App Router) - Frontend UI
- **Symfony** (PHP 8.4) - Business logic, API, calculation engine
- **Payload CMS** - Content (pages, SEO, media)
- **MySQL 8.0** - Business data (Symfony)
- **SQLite** - CMS data (Payload)
- **Redis** - Cache and queues
- **S3/MinIO** - Document storage

## Quick Start

### Prerequisites

- PHP 8.4+
- Node.js 20+
- MySQL 8.0
- Composer

### Symfony Backend

```bash
cd symfony
cp .env .env.local
# Edit .env.local: set DATABASE_URL for MySQL
composer install
php bin/console doctrine:migrations:migrate --no-interaction
php bin/console app:seed-data
php -S localhost:8080 -t public
```

### Next.js Frontend

```bash
cd nextjs
cp .env.example .env.local
# Set NEXT_PUBLIC_API_URL=http://localhost:8080
npm install
npm run dev
```

Open http://localhost:3000

### Payload CMS Admin

Payload is integrated in the Next.js app. After starting Next.js:

- Admin panel: http://localhost:3000/admin
- Create first user to access the CMS

### Docker (optional)

```bash
docker compose up -d
```

## API Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | /api/catalog | Categories + products |
| GET | /api/product/{slug} | Product by slug |
| POST | /api/calculate | Calculate price |
| POST | /api/estimate | Create estimate |
| GET | /api/estimate/{id} | Get estimate |
| POST | /api/order | Create order |
| POST | /api/lead | Create lead |
| POST | /api/chat | AI chat |
| GET | /api/documents/quote/{id} | PDF quote |

## Project Structure

```
├── nextjs/          # Next.js + Payload CMS
├── symfony/         # Symfony API
├── docker/          # Dockerfiles
└── docker-compose.yml
```

## License

Proprietary
