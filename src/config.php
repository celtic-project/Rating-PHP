<?php
/**
 * This page contains the configuration settings for the application.
 *
 * @author  Stephen P Vickers <stephen@spvsoftwareproducts.com>
 * @copyright  SPV Software Products
 * @license  http://www.gnu.org/licenses/gpl.html GNU General Public License, version 3
 */
###
###  Application settings
###
define('APP_NAME', 'Rating');
define('SESSION_NAME', 'php-rating');
define('VERSION', '4.1.0');

###
###  Database connection settings
###


define('DB_NAME', 'pgsql:host=ec2-54-247-96-153.eu-west-1.compute.amazonaws.com:5432;dbname=deefa96l5759vu');  // e.g. 'mysql:dbname=MyDb;host=localhost' or 'sqlite:php-rating.sqlitedb'
define('DB_USERNAME', 'xstgukhzztifyr');
define('DB_PASSWORD', '24f0d6f20790de16a863d49767aa8bb8c9778fdcc7a48080986898ef688b29f4');
define('DB_TABLENAME_PREFIX', '');
// Specify a prefix (starting with '/') when the REQUEST_URI server variable is missing the first part of the real path
define('REQUEST_URI_PREFIX', '');

###
###  Security settings
###
const SIGNATURE_METHOD = 'RS256';
const KID = '1098u13m1lkm21121km1l2k';  // A random string to identify the key value
const PRIVATE_KEY = <<< EOD
-----BEGIN RSA PRIVATE KEY-----
MIIEpAIBAAKCAQEA6MiJkYoWkOJTEaOtrq54pjeWA0g7dwJ4/qiThODXFQBcRjiF
esb0EpAUOk8pz95MtfEGrurLKKjF0Z19cpcUu6eq7wObSiYNLCN7mHLj40YzhQR1
kWcJrEm3oA8xgrUv3DeySTKxfiJZXj2jecHgQdBv4mHc7wxIzK18xS27ynVl1xWT
NSrwCJzLNZBwV8DOhPxySjHOXZsaohHIyOFeNfRVlkinbGgnv0O9oIOnYDn6pgXk
W4mwUi6sgyibdPatJslwm+ZWSC8LiiecMxq42fBMq2NpoP/uUr9qNNiJt/en1/70
LbrU5wEPv8z51NqbyMNLAZXxBTqcO6nSUwVa/QIDAQABAoIBAAxB+xSB2/xXHp6w
KsYnA81k4e5cUF1M8Qgf4ly95jWB5loAQe8cKOANXDNR1dbPUuTFw19Ul8wVTw1h
qKhvEjVrd9HMM9IsvMbVO33kluFx4eagPHyim1zKKPQxuJ60YcfL2wSFudj1gBU5
U7FmpyNwEWQvWQ3xbKfyfr53UQsxF6YulWqNqGgRo/Rm1TMXWrVtS2GeGaian+Sb
pRMTYop/snBAmAQsbZD8RCIvZrjPbFpYL4/mBII9QBh3R58Ul6foGGT8+OsSshX/
SgaCVnSzP+7h7j3bO7D3cTq2muidsJhCplbROi2W+grt8rHnk5oVAgIPmzwfLXas
QyGIO7UCgYEA/6BsxU4tZW0+Ot5dagxcbG3mw3vAJ9UpB1MQou3oaj/DpiTZ0Es5
glzm+y6cxm2E1ojzyywuvLWtrT8hUkiQMltmshMEzJTCn5k1Cxtp/yI0i7/1nvcW
PpMz/6QSA3SAmorcEL9Zc7KUQ/h029hekSt4jOA8TVbFSHwrASR/tYsCgYEA6R+S
W24ktlHtI4NtXFm+AlaVb/s2uuFxOPm66/C/aqkZGayzlin6Ehp+NN+TlHOMxjeW
ceZRWhxpFfx9YEqThv9Uxp1A8S447Y8TDnlcPEYu/AhLqLRWUlbKYbn1XGio4t+N
XlCqct+fyTB1vk2ebI8m+wcxVkGahdThRTd4kpcCgYEA15GPCJiT2dvVRcmt1zeT
XXEU0Ld0ZWLyFZYsCmo8vBUHxf1/nZNCbTgxJZO6a++BvXWCukyJIWTIkLgTPpOo
3n6LzRIS0v+EXRjTTYmRyrEqxMtds+/E14JFsIjJFBbUOP9u88SaB+KJ/APzcE43
+Y5CO/MBh2rsNeNYVL9V318CgYEA1cMLcycWJtAMwGm9F9d6ca5vLNWPo+Eg7vuf
OMXy35za1T4VFna7RWphm53/NzqVNRS3sQ1eP2CZw31YgbkgecMbO1vqtryJmtt8
v+LsfqBkaNo6diGWnb3TqbTYlbmqUw5mFLum79q6K8Nx0+RchnLlbX108gEJ7fn7
7nok51UCgYAB5pHn+Sw4/ulBvTwy39rkwZZtTSJCP9tUvx/pH0+fCuAbGf/to4iI
CXO1z2HsuHvuOqq8aCloglxJcBpmoLNhe5zK7uEQMsRtAYPjHBaR/82cE39DkcXU
RAtWnn0s4oCfx361Rz1vK0rcGzwzAxZkluMtgF6Ctoof7Sl8qX618Q==
-----END RSA PRIVATE KEY-----
EOD;

###
###  Registration settings
###
define('AUTO_ENABLE', false);
define('ENABLE_FOR_DAYS', 0);
?>
