ARG PHP_IMAGE

FROM composer:2.11.2 AS composer

# An official PHP image with Composer, which unpacks the packages with the image's busybox unzip.
FROM ${PHP_IMAGE}
COPY --from=composer /usr/bin/composer /usr/bin/composer
# patch applies the changes of a flow scenario's steps.txt.
RUN apk add --no-cache patch
