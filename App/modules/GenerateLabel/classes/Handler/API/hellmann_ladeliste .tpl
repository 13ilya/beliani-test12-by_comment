<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Title</title>
    <style>
        body {
            font-size: 14px;
        }
    </style>
</head>
<body>

<table width="100%">
    <tr>
        <td width="33%">
            Absender:<br>
            {$recipient.name}<br>
            {$recipient.company}<br>
            {$recipient.street} {$recipient.house}<br>
            {$recipient.country} {$recipient.zip} {$recipient.city}
        </td>
        <td width="33%">
            <p>
                Logistikdienstleister: <br>
                <b>Hellmann Worldwide Logistics</b><br>
                <b>GmbH & Co.KG</b><br>
                <b>Europastrasse 1</b><br>
                <b>DE- 31275 Lehrte</b>
            </p>
        </td>
        <td width="33%">
            <img src="data:image/jpeg;base64,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">
        </td>
    </tr>
</table>

<table width="100%">
    <tr>
        <td colspan="3">
            <span style="font-size: 18px; font-weight: bold">Übergabeschein / Ladeliste</span>
        </td>
    </tr>
    <tr>
        <td colspan="2">
            Dienstleistung: hellmann national - Lehrte
        </td>
        <td>
            Seite: <br>
            Datum:<br>
            Uhrzeit:
        </td>
    </tr>
</table>

<table>
    <tr>
        <td>Empfänger:</td>
        <td></td>
        <td>
            Referenz:<br>
            Frankatur:
        </td>
        <td></td>
    </tr>
    <tr>
        <td>Texte:</td>
        <td></td>
        <td></td>
        <td></td>
    </tr>
    <tr>
        <td>Zeichen & Nr.:</td>
        <td>Anzahl und Verpackung Aussen / Innen:</td>
        <td>Inhalt:</td>
        <td>Anz. / LxBxH in cm / Gewicht in Kg</td>
    </tr>
    <tr>
        <td colspan="4">

        </td>
    </tr>
</table>

<table>
    <tr>
        <td>Gesamtgewicht:</td>
        <td>Gesamtcolli:</td>
        <td>Gesamt Ladehilfsmittel:</td>
    </tr>
</table>

<table>
    <tr>
        <td>Datum:</td>
        <td>Kennz:</td>
        <td>Unterschrift (Fahrer):</td>
    </tr>
</table>

<p>Für alle Geschäftsabschlüsse gelten die Allgemeinen Deutschen Spediteurbedingungen (ADSP) neueste Fassung.</p>

</body>
</html>