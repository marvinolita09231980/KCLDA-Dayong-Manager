$php = 'C:\laragon\bin\php\php-8.2.19-Win32-vs16-x64\php-8.2.19-Win32-vs16-x64\php.exe'
$ext = 'C:\laragon\bin\php\php-8.2.19-Win32-vs16-x64\php-8.2.19-Win32-vs16-x64\ext'
& $php -n -d "extension_dir=$ext" -d extension=openssl -d extension=mbstring -d extension=fileinfo -d extension=intl -d extension=pdo_sqlite -d extension=sqlite3 artisan serve
