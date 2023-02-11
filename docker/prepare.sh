#!/bin/sh -ex
#

cp -av web-gui docker/www
cp -av template docker/www/api/
cp -av bin/wg.php docker/www/api/
