FROM nginx:1.27-alpine

# Hapus default config bawaan image, ganti dengan config HRIS
RUN rm -f /etc/nginx/conf.d/default.conf

COPY default.conf /etc/nginx/conf.d/default.conf

WORKDIR /var/www/html

EXPOSE 80

CMD ["nginx", "-g", "daemon off;"]
