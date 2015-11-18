<!doctype html>
<html>
<head>
    <title>Short URL</title>
    <style media="handheld"></style>
    <meta name="viewport" content="width=device-width, initial-scale=1"/>
    <link rel="shortcut icon" href="favicon.ico" type="image/x-icon"/>
</head>
<body>
<h2><a href="<?php echo $short['url'] ?>"><?php echo $short['url'] ?></a></h2>
<h3>Visit Count: <?php echo $short['visit'] ?></h3>
<?php
if (stripos($_SERVER['HTTP_USER_AGENT'], 'Macintosh') !== false ||
    stripos($_SERVER['HTTP_USER_AGENT'], 'Windows NT') !== false ||
    stripos($_SERVER['HTTP_USER_AGENT'], 'X11') !== false):  //Display the QR Code directly on desktop
?><img src="//api.qrserver.com/v1/create-qr-code/?data=<?php echo rawurlencode($short['url']) ?>&size=240x240"><?php
else: // Display the QR Code after clicking for saving data on mobile devices
?><a id="getQR" href="javascript:var i=document.createElement('img');i.src='//api.qrserver.com/v1/create-qr-code/?data=<?php echo rawurlencode($short['url']) ?>&size=240x240';var b=document.getElementsByTagName('body')[0];b.appendChild(i);b.removeChild(document.getElementById('getQR'));">QR Code</a><?php
endif;
?>
</body>
</html>
