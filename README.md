# MAILING REWORKED FOR <a href="https://www.dolibarr.org">DOLIBARR ERP CRM</a>

## Features
Reprise du module de mailing de masse de Dolibarr, en essayant de mettre en oeuvre une interface plus moderne et des fonctionnalités de 
création de mail facilitées.   
Développée pour <a href="http://www.effios.fr/">Effios</a> par <a href="https://github.com/Marshlyin"> Flavien Belli </a>

<!--
![Screenshot mailingreworked](img/screenshot_mailingreworked.png?raw=true "Mailingreworked"){imgmd}
-->

D'autres modules sont disponibles sur  <a href="https://www.dolistore.com" target="_new">Dolistore.com</a>.


Install
-------

### From the ZIP file and GUI interface

- If you get the module in a zip file (like when downloading it from the market place [Dolistore](https://www.dolistore.com)), go into
menu ```Home - Setup - Modules - Deploy external module``` and upload the zip file.


Note: If this screen tell you there is no custom directory, check your setup is correct: 

- In your Dolibarr installation directory, edit the ```htdocs/conf/conf.php``` file and check that following lines are not commented:

    ```php
    //$dolibarr_main_url_root_alt ...
    //$dolibarr_main_document_root_alt ...
    ```

- Uncomment them if necessary (delete the leading ```//```) and assign a sensible value according to your Dolibarr installation

    For example :

    - UNIX:
        ```php
        $dolibarr_main_url_root_alt = '/custom';
        $dolibarr_main_document_root_alt = '/var/www/Dolibarr/htdocs/custom';
        ```

    - Windows:
        ```php
        $dolibarr_main_url_root_alt = '/custom';
        $dolibarr_main_document_root_alt = 'C:/My Web Sites/Dolibarr/htdocs/custom';
        ```
        
### From a GIT repository

- Clone the repository in ```$dolibarr_main_document_root_alt/mailingreworked```

```sh
cd ....../custom
git clone git@github.com:Marshlyin/mailingreworked.git mailingreworked
```

### <a name="final_steps"></a>Final steps

From your browser:

  - Log into Dolibarr as a super-administrator
  - Go to "Setup" -> "Modules"
  - You should now be able to find and enable the module



Licenses
--------

### Main code
This software is under GPL3 licence.

![GPL](https://www.dolibiz.com/wp-content/uploads/2017/09/gpl.png "Licence GPL v3")


#### Documentation

//TODO


![GFDL logo](img/gfdl.png)
