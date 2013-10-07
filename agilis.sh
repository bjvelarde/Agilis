#!/bin/bash
SCRIPT=$(readlink -f $0)
SCRIPTPATH=`dirname $SCRIPT`
CMD="php $SCRIPTPATH/agilis.php"
ERRMSG="Usage: agilis <appname>"
if [[ $1 ]]
then
  $CMD $1
else
  echo $ERRMSG
fi