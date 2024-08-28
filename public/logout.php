<?php
require(__DIR__ . '/../src/common.php');
MySession::unsetToken();
html_header('iiet.pl');
?>
<div class="w-100" style="max-width: 400px;">
<p>
Wylogowano cię.
Możesz wciąż być zalogowany w pozostałych serwisach,
bo nie do końca przemyślałem jak to wszystko będzie działać
i nie mam możliwości cię wylogować.
Ups.
</p>
</div>
<?php html_footer();
