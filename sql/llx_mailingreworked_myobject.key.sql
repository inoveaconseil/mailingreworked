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
-- along with this program.  If not, see http://www.gnu.org/licenses/.


-- BEGIN MODULEBUILDER INDEXES
ALTER TABLE llx_mailingreworked_myobject ADD INDEX idx_fieldobject (fieldobject);
-- END MODULEBUILDER INDEXES

--ALTER TABLE llx_mailingreworked_myobject ADD UNIQUE INDEX uk_mailingreworked_myobject_fieldxy(fieldx, fieldy);

--ALTER TABLE llx_mailingreworked_myobject ADD CONSTRAINT llx_mailingreworked_myobject_fk_field FOREIGN KEY (fk_field) REFERENCES llx_mailingreworked_myotherobject(rowid);

