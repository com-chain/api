DROP TABLE IF EXISTS comchain.testtransactions;

CREATE TABLE comchain.testtransactions (
  add1 text,
  add2 text,
  status int,
  hash text,
  time bigint,
  direction int,
  
 
  recieved int,
  sent int,
  tax int,
  type text,
  block text,
  
  linked_hash text,
  
  delegate text,
  message_from text,
  message_to text,
  
  store_id text,
  store_ref text,
  tr_attempt_date text,
  tr_attempt_nb int,
  wh_status int,
  PRIMARY KEY ((add1,status), time) )
  WITH  CLUSTERING ORDER BY (time ASC) ;
  
CREATE INDEX ON comchain.testtransactions (hash);



GRANT MODIFY ON comchain.testtransactions TO transactions_rw;

GRANT SELECT ON comchain.testtransactions TO transactions_rw;

GRANT SELECT ON comchain.testtransactions TO transactions_ro;
  
TRUNCATE comchain.testtransactions;

1980468

