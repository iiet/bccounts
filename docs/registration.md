# O rejestracji
Rejestrowanie nowych użytkowników jest niestety dość skomplikowaną kwestią z dwóch powodów:
1. Nie otrzymujemy od razu pełnej listy studentów - AFAIK przechodzimy przez kilka iteracji zanim w końcu dostaniemy listę wszystkich osób.
2. Każdy student musi sobie ręcznie założyć uczelnianego maila - nie możemy więc po prostu wysłać od razu każdemu maila z linkiem do rejestracji i mieć to z głowy.

## Oryginalne rozwiązanie circa 2014
Oryginalne accountsy wymagały od ludzi podania indywidualnego "tokenu" (uzyskanego od kogoś z BITu po zweryfikowaniu że jest się studentem).
Na stronie do rejestracji trzeba było zweryfikować swoje imię i nazwisko, i datę urodzenia (choć w rzeczywistości nie była ona chyba sprawdzana?).

Współcześnie weryfikowalibyśmy pewnie numer indeksu.

Z zalet: nie wymaga do dostępu do poczty, i jest dość proste.
Z wad:
- Jeśli rozdajemy tokeny, wymaga to sporo ręcznej pracy.
- Jeśli ich nie rozdajemy, można założyć konto na cudze dane jeśli zna się jego numer indeksu - więc trzeba być przygotowanym na taką sytuację.
- Wymaga to porównywania wpisanego imienia i nazwiska z tym przechowanym w bazie danych.
  Nawet w przypadku Polaków nie jest to łatwe, a w ogólnym przypadku
  [nawet nie warto próbować](https://www.kalzumeus.com/2010/06/17/falsehoods-programmers-believe-about-names/).

Nie mamy raczej dostępnych żadnych innych informacji które mogłyby unikatowo identyfikować osobę.

### Późniejsze rozwiązanie
Dostajemy listę studentów, zakładamy im konta w systemie, i resetujemy im hasło by dostali maila.
Jest to głupie, ale "działa". Z grubsza.
Wymaga to sporo ręcznej roboty ze względów wymienionych we wstępie.

## Aktualny (plan) rozwiązania
### Importowanie użytkowników
Nowo importowani użytkownicy dostają częściowo zainicjalizowane wpisy w tablicy `users`,
i token rejestracyjny w tablicy `regtokens`.

Importowani użytkownicy są deduplikowani po adresie email (`INSERT OR IGNORE`),
więc można bez problemu wykonać skrypt kilka razy na różnych wersjach tabeli.
Lepiej byłoby deduplikować na bazie numerów indeksu, ale nie są one `UNIQUE`,
a użyte adresy email i tak będą bazowane na numerze indeksu (`INDEKS@student.agh.edu.pl`).

Generowany jest też dodatkowy "token rejestracji" wspólny dla całego rocznika,
ale o nim później.

Informacje o rejestracji i wspólny token są przekazywane każdemu nowemu studentami.

### Rejestracja
Nowi studenci wchodzą na stronę w stylu `/tokenreq.php?token=WSPOLNY_TOKEN`.
Proszeni są o założenie uczelnianego konta pocztowego,
a po wpisaniu swojego numeru indeksu wysyłany jest im mail z linkiem do rejestracji
zawierającym indywidualny token.

Po wejściu w link z maila studentom wyświetlają się przekazane nam ich dane
(by mogli zauważyć i zgłosić ewentualne nieścisłości),
i pola na nazwę użytkownika i hasło.

Dzięki temu, że każdy sam prosi o maila z tokenem,
nie musimy się martwić o dosyłanie tokenów osobom które jeszcze nie miały własnego maila.

### Nadużycie pierwszego formularza
Każdy z dostępem do formularza rejestracyjnego może spróbować przeiterować przez każdy możliwy numer indeksu.
Może to spowodować dwa problemy:
1. Dałoby mu to listę numerów indeksu studentów informatyki.
   O ile nie byłyby one powiązane z faktycznymi imieniami i nazwiskami, to i tak może być problemem.
   Nawet jeśli strona pokazująca się przy istniejącym i nieistniejącym numerze indeksu wygląda tak samo,
   to prawdopodobnie będzie dało się stwierdzić czy dany numer jest w bazie na podstawie ilości czasu poświęconej przez serwer na odpowiedź.
2. Wysłalibyśmy w krótkim czasie bardzo dużo maili i zaczęlibyśmy trafiać do spamu.

Wymaganie tokena rocznika (które powinno być sprawdzone przed wszystkim innym) ma zaradzić pierwszemu problemowi.

Drugi problem zwraca uwagę na potrzebę rate limitingu.
Globalny rate limit nie ma za wiele sensu, więc ~~z każdym tokenem rejestracyjnym przechowywany jest czas, w którym został on ostatnio wysłany~~.
Każdy użytkownik (w tabeli `users`) ma pole `last_email`.
Będzie ono też używane dla resetów hasła etc.

### Czemu by nie połączyć formularzy?
Połączenie obu formularzy w jeden może się wydawać dobrym pomysłem na uproszczenie całego procesu.

Co jednak w przypadku, gdy zostanie wprowadzonych kilka różnych danych dla jednego numeru indeksu?

W którym momencie "rezerwujemy" nick?
Jeśli rezerwujemy nick przy wypełnieniu formularza,
pozwalamy jednemu użytkownikowi zarezerwować naraz sporą liczbę nazw.
Jeśli rezerwujemy go dopiero po kliknięciu w link potwierdzający,
możliwa jest sytuacja, w której ktoś zajął nasz nowy nick pomiędzy wypełnieniem formularza
a potwierdzeniem rejestracji - co było by dość frustrujące dla użytkownika.
