# bccounts

Nasze stare
[SSO](https://git.iiet.pl/iiet/iietusers/)
zapada się pod własnym ciężarem.
Jest to dość spory projekt.
Korzysta z 31 gemów[^telemetry],
a według [cloc](https://github.com/AlDanial/cloc) składa się z prawie 5KLoC i 208 (!) plików.
Był też deployowany w dość nietypowy (jak na BIT) sposób,
za pomocą [dokku](https://dokku.com/) -- bo czemu nie?
Autorzy w końcu ogarniają to narzędzie...

Autorzy oryginalnym accountsów jednak dawno już tu nie studiują.
Pakiety z których korzystaliśmy wprowadzały przełomowe zmiany -
by accountsy dalej działały[^outdated] członkowie infry próbowali je aktualizować.
Nikt jednak porządnie się tym nie zajął, i przez lata psuły się powoli różne funkcje.
Doszliśmy do momentu w którym nie działa już prawie nic poza najbardziej podstawowymi funkcjami,
co dość mocno utrudnia mi robotę.

**bccounts** stara się być przeciwieństwem poprzednich accountsów.
Nie korzystam z żadnych bibliotek[^bootstrap].
Piszę je w PHP.
Jest to boleśnie zły język,
ale każdy admin BIT Infry i tak go musi znać (przez Dokuwiki),
a moje doświadczenia z Dokuwiki przekonały mnie że to jest dobry wybór na projekt który ma działać za 10 lat.

Nie korzystam z żadnego frameworka,
bo nie chcę oczekiwać od kolejnych adminów znajomości frameworka PHP
który akurat był popularny kiedy zaczynałem to pisać.
Jest to celowo "prymitywny" kod.

[^telemetry]: ...i z trzech zamkniętych narzędzi do telemetrii - Google Analytics, Airbrake, Newrelic.  w apce do sso. lol.

[^outdated]: Można po prostu nic nie aktualizować, co było główną taktyką BITu przez ostatnie parę lat.  Może to i działać dla małych projektów (stąd bccounts), ale przy korzystaniu z tylu bibliotek na pewno przynajmniej w paru z nich czychają się jakieś niezałatane błędy.

[^bootstrap]: Poza CSS Bootstrapa.  Jest to jeden plik którego nie muszę nigdy aktualizować, a nawet gdyby przestał jakimś cudem działać, to zepsuje tylko estetykę strony - a nie jej działanie.  Jego zalety przewyższają wady.

## jak odpalić
1. Tworzymy bazę danych - `sqlite db.sqlite '.read schema.sql'`.
2. Dostosowujemy config do swoich potrzeb.
3. `php -S localhost:8080 -t public` (oczywiście tylko do testów - config produkcyjny ~~jest~~ będzie na [nusible](https://git.iiet.pl/iiet/nusible/tree/main))

### testowanie OAuth
1. Wejdź na https://oauthdebugger.com/, ustaw poprawny Authorize URI (`http://localhost:8080/oauth.php/authorize`), i client ID. `response_type=code`, `response_mode=query`.
2. Przekopiuj "ciało POST" z kroku drugiego w debuggerze do `curl -H "Content-Type: application/x-www-form-urlencoded" -X POST -d @- localhost:8080/oauth.php/token`.
3. Przekopiuj uzyskany access token do `curl -H "Authorization: Bearer $(cat)" localhost:8080/oauth.php/userinfo`.

Zakładam że resztę wykombinujesz.

Bardzo przydałyby się też zaautomatyzowane testy.
