<?php

/*
| German validation messages. Rules not listed here fall back to English.
*/

return [
    'accepted' => ':Attribute muss akzeptiert werden.',
    'after' => ':Attribute muss ein Datum nach :date sein.',
    'after_or_equal' => ':Attribute muss ein Datum am oder nach :date sein.',
    'alpha' => ':Attribute darf nur Buchstaben enthalten.',
    'alpha_dash' => ':Attribute darf nur Buchstaben, Ziffern, Binde- und Unterstriche enthalten.',
    'alpha_num' => ':Attribute darf nur Buchstaben und Ziffern enthalten.',
    'array' => ':Attribute muss eine Liste sein.',
    'before' => ':Attribute muss ein Datum vor :date sein.',
    'before_or_equal' => ':Attribute muss ein Datum am oder vor :date sein.',
    'between' => [
        'array' => ':Attribute muss zwischen :min und :max Einträge haben.',
        'file' => ':Attribute muss zwischen :min und :max Kilobyte groß sein.',
        'numeric' => ':Attribute muss zwischen :min und :max liegen.',
        'string' => ':Attribute muss zwischen :min und :max Zeichen lang sein.',
    ],
    'boolean' => ':Attribute muss ja oder nein sein.',
    'confirmed' => 'Die Wiederholung von :attribute stimmt nicht überein.',
    'current_password' => 'Das Passwort ist falsch.',
    'date' => ':Attribute muss ein gültiges Datum sein.',
    'date_equals' => ':Attribute muss der :date sein.',
    'date_format' => ':Attribute muss dem Format :format entsprechen.',
    'decimal' => ':Attribute muss :decimal Nachkommastellen haben.',
    'different' => ':Attribute und :other müssen sich unterscheiden.',
    'digits' => ':Attribute muss :digits Ziffern lang sein.',
    'digits_between' => ':Attribute muss zwischen :min und :max Ziffern lang sein.',
    'distinct' => ':Attribute kommt doppelt vor.',
    'email' => ':Attribute muss eine gültige E-Mail-Adresse sein.',
    'enum' => 'Die Auswahl für :attribute ist ungültig.',
    'exists' => 'Die Auswahl für :attribute ist ungültig.',
    'filled' => ':Attribute muss ausgefüllt sein.',
    'gt' => [
        'numeric' => ':Attribute muss größer als :value sein.',
        'string' => ':Attribute muss länger als :value Zeichen sein.',
    ],
    'gte' => [
        'numeric' => ':Attribute muss mindestens :value sein.',
        'string' => ':Attribute muss mindestens :value Zeichen lang sein.',
    ],
    'iban' => ':Attribute muss eine gültige IBAN sein.',
    'in' => 'Die Auswahl für :attribute ist ungültig.',
    'integer' => ':Attribute muss eine ganze Zahl sein.',
    'lt' => [
        'numeric' => ':Attribute muss kleiner als :value sein.',
        'string' => ':Attribute muss kürzer als :value Zeichen sein.',
    ],
    'lte' => [
        'numeric' => ':Attribute darf höchstens :value sein.',
        'string' => ':Attribute darf höchstens :value Zeichen lang sein.',
    ],
    'max' => [
        'array' => ':Attribute darf höchstens :max Einträge haben.',
        'file' => ':Attribute darf höchstens :max Kilobyte groß sein.',
        'numeric' => ':Attribute darf höchstens :max sein.',
        'string' => ':Attribute darf höchstens :max Zeichen lang sein.',
    ],
    'min' => [
        'array' => ':Attribute muss mindestens :min Einträge haben.',
        'file' => ':Attribute muss mindestens :min Kilobyte groß sein.',
        'numeric' => ':Attribute muss mindestens :min sein.',
        'string' => ':Attribute muss mindestens :min Zeichen lang sein.',
    ],
    'numeric' => ':Attribute muss eine Zahl sein.',
    'password' => [
        'letters' => ':Attribute muss mindestens einen Buchstaben enthalten.',
        'mixed' => ':Attribute muss Groß- und Kleinbuchstaben enthalten.',
        'numbers' => ':Attribute muss mindestens eine Ziffer enthalten.',
        'symbols' => ':Attribute muss mindestens ein Sonderzeichen enthalten.',
        'uncompromised' => ':Attribute ist in einem Datenleck aufgetaucht. Bitte wähle ein anderes.',
    ],
    'regex' => 'Das Format von :attribute ist ungültig.',
    'required' => ':Attribute ist ein Pflichtfeld.',
    'required_if' => ':Attribute ist ein Pflichtfeld, wenn :other :value ist.',
    'required_with' => ':Attribute ist ein Pflichtfeld, wenn :values angegeben ist.',
    'same' => ':Attribute und :other müssen übereinstimmen.',
    'size' => [
        'array' => ':Attribute muss genau :size Einträge haben.',
        'numeric' => ':Attribute muss genau :size sein.',
        'string' => ':Attribute muss genau :size Zeichen lang sein.',
    ],
    'string' => ':Attribute muss ein Text sein.',
    'timezone' => ':Attribute muss eine gültige Zeitzone sein.',
    'unique' => ':Attribute wird bereits verwendet.',
    'url' => ':Attribute muss eine gültige Adresse (URL) sein.',

    'custom' => [],

    'attributes' => [
        'email' => 'E-Mail-Adresse',
        'password' => 'Passwort',
        'current_password' => 'Aktuelles Passwort',
        'name' => 'Name',
        'role' => 'Rolle',
        'locale' => 'Sprache',
    ],
];
