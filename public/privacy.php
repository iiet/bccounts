<?php
/* Mostly here to deal with GDPR. See articles 12-14 and article 6.
 * https://eur-lex.europa.eu/legal-content/PL/TXT/HTML/?uri=CELEX:32016R0679#d1e2184-1-1
 * Honestly not as bad as I've thought, it's a relatively short read.
 */

require(__DIR__ . '/../src/common.php');

html_header("Polityka prywatności");
?>
<div class="w-100" style="max-width: 800px">
<h2>Polityka prywatności</h2>
<p>
Twoje dane przetwarzane są przez
<a href="https://knbit.edu.pl/">Koło Naukowe BIT</a>.
Przetwarzamy je w celach współpracy z Wydziałem Informatyki (dalej: Wydziałem)
oraz umożliwienia Ci dostępu do dodatkowych usług przeznaczonych dla studentów Wydziału,
na podstawie
<a href="https://eur-lex.europa.eu/legal-content/PL/TXT/HTML/?uri=CELEX:32016R0679#d1e1903-1-1">artykułu 6 RODO, lit. a) i f)</a>.
Dostęp do nich mają członkowie BIT zajmujący się tymi usługami, jak i starości roku, na którym studiujesz.
</p>
<p>
Władze Wydziału przekazały nam Twój <b>numer indeksu</b>, <b>imię</b>, i <b>nazwisko</b>.
Na ich bazie uzyskaliśmy też Twój uczelniany <b>adres email</b> &ndash;
chyba, że osobiście podałeś nam inny adres.
Przy rejestracji podajesz nam też <b>nazwę użytkownika</b> i <b>hasło</b>
(które przechowujemy za pomocą
<a href="https://en.wikipedia.org/wiki/Bcrypt">bcrypt</a>).
Jeśli tego nie zrobisz, nie uzyskasz dostępu do naszych usług,
i nie uzyskasz korzyści wynikających z naszej współpracy z Wydziałem.
</p>
<p>
Przechowujemy też <b>rok rozpoczęcia studiów</b> na Wydziale, <b>stopień</b> tych studiów, <b>czas rejestracji, ostatniej zmiany hasła, i ostatniego logowania</b>.
Do tego przechowujemy listę aktywnych sesji, każdą z <b>adresem IP</b>, z którego się zalogowałeś.
</p>
<p>
Po zalogowaniu przechowujemy też w twojej przeglądarce ciasteczko identyfikujące sesję.
</p>
<p>
Dla celów współpracy z wydziałem musimy przechowywać Twoje dane do momentu ukończenia przez Ciebie studiów -
ale <b>nie usuwamy ich automatycznie po zakończeniu studiów</b>, by pozwolić Ci na dalszy dostęp do naszych usług (do tego ciężko z całą pewnością automatycznie stwierdzić, kiedy ktoś już skończył studia).
Jeśli zakończyłeś już studia, możesz do nas napisać z prośbą o ich usunięcie.
</p>
<p class="my-0">
Musimy wymienić Ci jeszcze kilka innych przysługujących Ci praw:
</p>
<ul>
	<li>Możesz nas poprosić o dostęp, sprostowanie, lub ograniczenie przetwarzania twoich danych.</li>
	<li>Masz prawo wnieść sprzeciw wobec przetwarzania danych.</li>
	<li>Masz prawo wnieść skargę do organu nadzorczego.</li>
	<li>Masz prawo zapisać się na studia na Wydziale Ceramiki.</li>
	<!-- Prawo do przenoszenia chyba nas nie dotyczy ze względu na art. 20 ust. 3 -->
</ul>
</div>
<?php
html_footer();
