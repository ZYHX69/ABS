This project uses **Ratchet**, a PHP WebSocket library, to handle real‑time communication. All PHP dependencies, including Ratchet and `vlucas/phpdotenv`, are managed with Composer.

## Environment Configuration

The database credentials are **not hardcoded**. Instead, they are loaded from a `.env` file.

1. Copy the example environment file:
   ```bash
   cp .env.example .env