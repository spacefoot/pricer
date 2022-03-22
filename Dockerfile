FROM php:7.4-cli

RUN apt update && apt -y install git

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer --version=1.10.24
