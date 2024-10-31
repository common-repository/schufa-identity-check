<div class="wrap">
    <h2>Bisherige Prüfungsergebnisse</h2>
    <?php
    global $wpdb;
    $results = $wpdb->get_results('SELECT * FROM '.$wpdb->prefix.'identitycheck_requests');
    if(count($results) == 0):
        ?>
        <p>Es sind noch keine Prüfungen durchgeführt worden</p>
    <?php else: ?>
        <table>
            <thead>
            <tr>
                <td>Vorname</td>
                <td>Nachname</td>
                <td>Straße</td>
                <td>PLZ</td>
                <td>Ort</td>
                <td>Geburtsdatum</td>
                <td>Bestanden?</td>
                <td>Prüfung war am</td>
            </tr>
            </thead>
            <tbody>
            <?php

            foreach($results as $result):
                ?>
                <tr>
                    <td>
                        <?php echo $result->first_name ?>
                    </td>
                    <td>
                        <?php echo $result->last_name ?>
                    </td>
                    <td>
                        <?php echo $result->street ?>
                    </td>
                    <td>
                        <?php echo $result->zipcode ?>
                    </td>
                    <td>
                        <?php echo $result->city ?>
                    </td>
                    <td>
                        <?php echo $result->birthdate ?>
                    </td>
                    <td>
                        <?php
                        switch($result->result){
                            case "1":
                                echo "Ja";
                                break;
                            case "0":
                                echo "Nein";
                                break;
                        }
                        ?>
                    </td>
                    <td>
                        <?php echo $result->time ?>
                    </td>
                    <td>
                        <button type="button" onclick="deleteResult(<?php echo $result->id ?>)" class="button button-primary">Ergebnis löschen</button>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>