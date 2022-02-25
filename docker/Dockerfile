FROM vmlu/webox:nginx-php

LABEL version="1.0.0" \
      maintainer="wang@rehiy.com"

ADD www /ifs/www
ADD deploy /ifs/deploy

RUN sh /ifs/deploy

ENTRYPOINT ["/sbin/init"]

EXPOSE 80 443
