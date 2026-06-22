<?php
echo session_save_path() ?: ini_get('session.save_path');
echo "\n";
phpinfo(); // cari baris "session.save_path"