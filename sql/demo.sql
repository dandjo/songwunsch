-- Demo repertoire to try things out: 50 songs for the `songs` table.
--
-- Requires the table to exist -- either through sql/schema.sql (in the
-- Docker stack that runs first) or because the application has already
-- created it. Lengths in seconds.

-- The client must speak UTF-8, otherwise umlauts end up double-encoded in
-- the database (the mysql CLI uses latin1 depending on the version).
SET NAMES utf8mb4;

INSERT INTO `songs` (`artist`, `title`, `length_sec`, `genre`) VALUES
('Elvis Presley',            'Jailhouse Rock',              146, 'Rock ''n'' Roll'),
('Elvis Presley',            'Can''t Help Falling in Love', 180, 'Ballade'),
('Chuck Berry',              'Johnny B. Goode',             161, 'Rock ''n'' Roll'),
('Bill Haley & His Comets',  'Rock Around the Clock',       128, 'Rock ''n'' Roll'),
('Little Richard',           'Tutti Frutti',                143, 'Rock ''n'' Roll'),
('Jerry Lee Lewis',          'Great Balls of Fire',         111, 'Rock ''n'' Roll'),
('The Beatles',              'Twist and Shout',             155, 'Beat'),
('The Beatles',              'Hey Jude',                    431, 'Pop'),
('The Rolling Stones',       'Satisfaction',                224, 'Rock'),
('Roy Orbison',              'Oh, Pretty Woman',            179, 'Pop'),
('Ray Charles',              'Hit the Road Jack',           121, 'Soul'),
('Aretha Franklin',          'Respect',                     147, 'Soul'),
('Otis Redding',             'Sittin'' on the Dock of the Bay', 163, 'Soul'),
('Wilson Pickett',           'Land of 1000 Dances',         148, 'Soul'),
('James Brown',              'I Got You (I Feel Good)',     167, 'Funk'),
('Tina Turner',              'Proud Mary',                  308, 'Rock'),
('Creedence Clearwater Revival', 'Bad Moon Rising',         141, 'Rock'),
('Johnny Cash',              'Ring of Fire',                156, 'Country'),
('Patsy Cline',              'Crazy',                       161, 'Country'),
('Frank Sinatra',            'Fly Me to the Moon',          148, 'Swing'),
('Frank Sinatra',            'New York, New York',          206, 'Swing'),
('Dean Martin',              'Sway',                        155, 'Swing'),
('Louis Armstrong',          'What a Wonderful World',      139, 'Jazz'),
('Ella Fitzgerald',          'Summertime',                  295, 'Jazz'),
('Glenn Miller',             'In the Mood',                 231, 'Swing'),
('The Beach Boys',           'Surfin'' U.S.A.',             146, 'Surf'),
('ABBA',                     'Dancing Queen',               231, 'Disco'),
('ABBA',                     'Mamma Mia',                   213, 'Pop'),
('Bee Gees',                 'Stayin'' Alive',              285, 'Disco'),
('Village People',           'Y.M.C.A.',                    287, 'Disco'),
('Donna Summer',             'Hot Stuff',                   231, 'Disco'),
('Queen',                    'Bohemian Rhapsody',           355, 'Rock'),
('Queen',                    'Don''t Stop Me Now',          209, 'Rock'),
('AC/DC',                    'Highway to Hell',             208, 'Rock'),
('Bon Jovi',                 'Livin'' on a Prayer',         249, 'Rock'),
('Michael Jackson',          'Billie Jean',                 294, 'Pop'),
('Madonna',                  'Like a Prayer',               339, 'Pop'),
('Falco',                    'Rock Me Amadeus',             199, 'Austropop'),
('Rainhard Fendrich',        'I Am From Austria',           288, 'Austropop'),
('STS',                      'Fürstenfeld',                 279, 'Austropop'),
('Wolfgang Ambros',          'Schifoan',                    203, 'Austropop'),
('Georg Danzer',             'Jö schau',                    213, 'Austropop'),
('Peter Cornelius',          'Du entschuldige i kenn di',   242, 'Austropop'),
('EAV',                      'Küss die Hand, schöne Frau',  238, 'Austropop'),
('Andreas Gabalier',         'I sing a Liad für di',        222, 'Volks-Rock'),
('Helene Fischer',           'Atemlos durch die Nacht',     221, 'Schlager'),
('Wolfgang Petry',           'Wahnsinn',                    229, 'Schlager'),
('Nena',                     '99 Luftballons',              233, 'NDW'),
('Udo Jürgens',              'Griechischer Wein',           235, 'Schlager'),
('Nicole',                   'Ein bisschen Frieden',        183, 'Schlager');
