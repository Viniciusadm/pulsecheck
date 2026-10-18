# Contributing

Thanks for your interest in PulseCheck.

1. Open an issue first for larger changes, so the approach can be agreed before you invest time.
2. Set up the project as described in [Local development](README.md#local-development).
3. Keep changes focused, add or update tests, and make sure everything passes:

   ```sh
   cd back && vendor/bin/pint && php artisan test
   cd web && npm run lint && npm run test && npm run build
   ```

4. Write code, comments and UI text in English; UI strings go in `web/src/i18n/en.ts`.
5. Explain notable design decisions in the pull request description.

Security problems should be reported privately; see [SECURITY.md](SECURITY.md).
