<?php
require(__DIR__ . '/../src/common.php');

html_header("Polityka prywatności");
?>
<div class="w-100" style="max-width: 800px">
<h2>Polityka prywatności</h2>
<h3 class="mt-3">Część "po ludzku"</h3>
<p>
	Ten serwis jest otwartoźródłowy,
	więc w razie niejasności możesz zawsze sprawdzić kod
	(w końcu studiujesz informatykę).
</p>
<p class="mb-0">
	Przechowujemy poniższe dane o każdym koncie:
</p>
<ul class="my-0">
	<li>Imię i nazwisko,</li>
	<li>Numer indeksu,</li>
	<li>Adres email (wygenerowany na bazie numeru indeksu, chyba że sam podałeś nam inny),</li>
	<li>Hasło, hashowane za pomocą algorytmu <a href="https://en.wikipedia.org/wiki/Bcrypt">bcrypt</a>,</li>
	<li>Rok rozpoczęcia studiów i stopień</li>
	<li>Czas rejestracji, zmiany hasła, i ostatniego logowania.</li>
</ul>
<p>
	Przechowujemy też listę aktywnych sesji wraz z adresami IP,
	z których one powstały (z których dany użytkownik się zalogował).
</p>
<p>
	<b>Nie usuwamy automatycznie kont po zakończeniu studiów</b>,
	ponieważ ciężko nam stwierdzić,
	kiedy kto kończy studia,
	a niektórzy chcą mieć dostęp do serwisu nawet po zakończeniu studiów.
	By usunąć konto należy zwrócić się do administratorów.
</p>
<p>
	Po zalogowaniu się do serwisu przechowujemy na twoim urządzeniu ciasteczko
	sesji wymagane do działania strony.
</p>
<h3>Część "po prawniczemu"</h3>
<p>
TODO :(
</p>
</div>
<?php
html_footer();
