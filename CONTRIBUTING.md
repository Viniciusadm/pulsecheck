# Contributing

Thanks for your interest in PulseCheck.

1. Open an issue first for larger changes, so the approach can be agreed before you invest time.
2. Set up the project as described in [Local development](README.md#local-development).
3. Keep changes focused, add or update tests, and make sure everything passes:

   ```sh
   cd back && vendor/bin/pint && php artisan test
   cd web && npm run lint && npm run test && npm run build
   ```

4. Write code and UI text in English. UI strings go in `web/src/i18n/en.ts` (and `ptBR.ts` for the Portuguese translation); backend messages use English sentences as keys with translations in `back/lang/pt-BR.json`.
5. Prefer clear names over comments. Endpoints, configuration and behavior changes need the README or `docs/API.md` updated in the same pull request.
6. Explain notable design decisions in the pull request description.

Security problems should be reported privately; see [SECURITY.md](SECURITY.md). Everyone taking part is expected to follow the [code of conduct](CODE_OF_CONDUCT.md).
