ARG APEXWEAVE_STACK=php:8.3
FROM registry.apexweaveapp.com:5000/apexweave-stack-${APEXWEAVE_STACK}
COPY . .
RUN --mount=type=cache,target=/root/.composer sh /opt/apexweave/build.sh
EXPOSE 80
HEALTHCHECK --interval=60s --timeout=10s --start-period=120s --retries=3 CMD curl -sf -o /dev/null http://localhost:80/ || exit 1
CMD ["sh", "/opt/apexweave/start.sh"]
CMD ["sh", "-c", "php artisan storage:link --force || true; php artisan migrate --force || true; sh /opt/apexweave/start.sh"]
