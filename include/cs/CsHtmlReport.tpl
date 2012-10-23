<!DOCTYPE html>
<html>
    <head>
        <meta charset='utf-8'>
        <title><?=$title?></title>
        <style>
            <?=$style?>
        </style>
    </head>
    <body>
        <h4><?=$title?></h4>
        <div>Issues: <?=$stats['errors_total']?>, Crap rating: <?=$stats['crap_rating']?>%
            <span style="float:right"><?=date('M d, Y, H:i:s')?></span>
        </div>
        <?=$report?>
    </body>
</html>

