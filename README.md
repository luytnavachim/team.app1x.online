# ROHDA 14-2 · team.app1x.online

Kleding- en selectieoverzicht voor **ROHDA Raalte JO14-2**. Live: [https://team.app1x.online](https://team.app1x.online)

## Wat erin zit

- Spelerskaarten, maten, bestellijst en CSV
- Posities live uit [scout.app1x.online](https://scout.app1x.online) (`scout_id` per speler)
- Maten invullen met pincode (`save.php`)

## Setup

1. Kopieer `config.example.php` naar `config.php` en vul database + pincode-hash in.
2. Apache: DocumentRoot op deze map, `DirectoryIndex index.php`.
3. MySQL-database `team_app1x` met tabellen `players`, `player_clothing`, `clothing_types`, `staff_members`, `staff_clothing`.

`config.php` en `.data/` staan niet in git (wachtwoorden, lockfile).
