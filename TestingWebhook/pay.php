<?php
echo '
<html>
  <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
  </head>
  <body>
    <img src="comchain.png" height="100px" style="margin:20px auto 10px auto; display:block;" />
    <h1 style="display:block;width:calc(100% - 60px);padding:0px 30px 0 30px;font-size:18px;text-align:center;">Bienvenue sur la page de démonstration du webhook Com-Chain</h1>
    <h2 style="display:block;width:calc(100% - 60px);padding:0px 30px 0 30px;font-size:14px;text-align:center;">Pour pouvoir faire le test vous devez disposer d\'un compte de test (Lemanopolis)</h2>
    
    <form  action="./testWebHook.php" method="get" style="display:block;width:calc(100% - 60px);padding:0px 30px 0 30px;font-size:14px;text-align:center;" >
      <label for="amount">Montant de la transaction (total à payer):</label>
      <input type="number" id="amount" name="amount" min="0.01" step="0.01" value="0.01"><br/>
      <input type="submit" value="Vers le payement" style="padding:5px 10px; margin-top:20px; font-size:14px;">
    </form>
  </body>
</html>';     
?>
