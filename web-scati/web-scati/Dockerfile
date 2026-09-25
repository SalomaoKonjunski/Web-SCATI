# Imagem para publicar o Web SCATI em uma hospedagem baseada em Docker
# (ex.: Render), já que PHP não é uma linguagem nativa lá. A pasta
# vendor/ já vem pronta no repositório (ver README.md), então não é
# necessário rodar "composer install" durante o build.
FROM php:8.2-apache

RUN docker-php-ext-install pdo pdo_mysql

# Permite que os arquivos .htaccess dentro de uploads/ (que bloqueiam
# execução de script e listagem de diretório nos anexos enviados pelos
# usuários) sejam respeitados pelo Apache.
RUN sed -ri 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf

COPY . /var/www/html/

RUN chown -R www-data:www-data /var/www/html/uploads

# Hospedagens como o Render esperam que o serviço escute na porta 10000.
RUN sed -ri 's/80/10000/g' /etc/apache2/ports.conf /etc/apache2/sites-enabled/000-default.conf
EXPOSE 10000
