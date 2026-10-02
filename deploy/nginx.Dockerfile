FROM nginx:1.25-alpine
COPY nginx/default.conf /etc/nginx/conf.d/default.conf
COPY api/public /var/www/html/public
RUN mkdir -p /var/www/html/storage/app && ln -s /var/www/html/storage/app/public /var/www/html/public/storage
