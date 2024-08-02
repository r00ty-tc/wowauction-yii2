#!/bin/bash
cd /var/www/virtual/nerfed.net/auction
./yii >/dev/null 2>/dev/null reduce-data/index
