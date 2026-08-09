# Usar la imagen oficial de PHP con Apache
FROM php:8.2-apache

# Instalar extensiones necesarias para MySQL
RUN docker-php-ext-install pdo pdo_mysql

# Copiar TODOS tus archivos a la carpeta del servidor web dentro del contenedor
COPY . /var/www/html/

# Dar permisos
RUN chmod -R 777 /var/www/html/uploads

# El TRUCO PARA RENDER:
# Apache escucha por defecto en el puerto 80, pero Render usa un puerto variable.
# Esta línea le dice a Apache que escuche en el puerto que Render le asigne.
ENV APACHE_RUN_USER www-data
ENV APACHE_RUN_GROUP www-data
ENV APACHE_LOG_DIR /var/log/apache2
RUN sed -i 's/Listen 80/Listen ${PORT}/g' /etc/apache2/ports.conf
RUN sed -i 's/:80/:${PORT}/g' /etc/apache2/sites-available/000-default.conf