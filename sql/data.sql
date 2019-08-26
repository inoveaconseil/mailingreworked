-- Copyright (C) 2019 Flavien Belli
--
-- This program is free software: you can redistribute it and/or modify
-- it under the terms of the GNU General Public License as published by
-- the Free Software Foundation, either version 3 of the License, or
-- (at your option) any later version.
--
-- This program is distributed in the hope that it will be useful,
-- but WITHOUT ANY WARRANTY; without even the implied warranty of
-- MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
-- GNU General Public License for more details.
--
-- You should have received a copy of the GNU General Public License
-- along with this program.  If not, see <http://www.gnu.org/licenses/>.

INSERT INTO llx_mailingreworked_myobject VALUES (
	1, 1, 'mydata'
);
-- AJOUT DE COLONNE POUR UN MAIL PAR DEFAUT LORS DE LA CREATION D'UNE TEMPLATE'
ALTER TABLE llx_c_email_templates ADD COLUMN fromMail varchar(256) DEFAULT 'dne-gar@education.gouv.fr';

-- TEMPLATE PAR DEFAUT
INSERT INTO `llx_c_email_templates`(`type_template`,`label`, `position`,`topic`, `content`, `fromMail`) VALUES ('Modèle Vierge','Modèle Vierge',1,' ',' ',' ');

-- SUPPRIMER LES TEMPLATES DOLIBARR
DELETE FROM `llx_c_email_templates` WHERE `llx_c_email_templates`.`rowid` = 1;
DELETE FROM `llx_c_email_templates` WHERE `llx_c_email_templates`.`rowid` = 2;
DELETE FROM `llx_c_email_templates` WHERE `llx_c_email_templates`.`rowid` = 3;
DELETE FROM `llx_c_email_templates` WHERE `llx_c_email_templates`.`rowid` = 4;
DELETE FROM `llx_c_email_templates` WHERE `llx_c_email_templates`.`rowid` = 5;
DELETE FROM `llx_c_email_templates` WHERE `llx_c_email_templates`.`rowid` = 6;
DELETE FROM `llx_c_email_templates` WHERE `llx_c_email_templates`.`rowid` = 7;


