USE prime_fit;
-- À importer une seule fois, après schema.sql, sur une base vierge.
INSERT INTO users (name,email,password_hash,role) VALUES ('Admin Horizon','admin@primefit.tn','$2b$12$eqtB/h6qqfdRC8liK7AdfOkqeUwhIm85WwBOqULPdi/nyQruMODMu','Admin');
INSERT INTO plans (name,duration_days,price) VALUES ('Mensuel',30,90.000),('Trimestriel',90,240.000),('Annuel',365,850.000),('Séances',30,65.000);
INSERT INTO members (first_name,last_name,phone,email,card_number,governorate,avatar_color) VALUES
('Amine','Ben Salah','+216 98 234 567','amine@example.tn','PF-1001','Tunis','violet'),('Mariem','Trabelsi','+216 22 456 789','mariem@example.tn','PF-1002','Sousse','rose'),('Youssef','Mansouri','+216 55 123 456','youssef@example.tn','PF-1003','Sfax','blue'),('Inès','Gharbi','+216 20 345 678','ines@example.tn','PF-1004','Monastir','green'),('Sarra','Chérif','+216 99 876 543','sarra@example.tn','PF-1005','Nabeul','gold'),('Ahmed','Khaldi','+216 26 567 890','ahmed@example.tn','PF-1006','Tunis','blue');
INSERT INTO subscriptions (member_id,plan_id,start_date,end_date,status,created_at) VALUES
(1,3,CURDATE()-INTERVAL 200 DAY,CURDATE()+INTERVAL 165 DAY,'active',NOW()-INTERVAL 1 DAY),
(2,2,CURDATE()-INTERVAL 80 DAY,CURDATE()+INTERVAL 4 DAY,'active',NOW()-INTERVAL 2 DAY),
(3,1,CURDATE()-INTERVAL 27 DAY,CURDATE()+INTERVAL 2 DAY,'active',NOW()-INTERVAL 3 DAY),
(4,3,CURDATE()-INTERVAL 100 DAY,CURDATE()+INTERVAL 265 DAY,'active',NOW()-INTERVAL 4 DAY),
(5,1,CURDATE()-INTERVAL 35 DAY,CURDATE()-INTERVAL 5 DAY,'active',NOW()-INTERVAL 5 DAY),
(6,2,CURDATE()-INTERVAL 30 DAY,CURDATE()+INTERVAL 60 DAY,'active',NOW()-INTERVAL 6 DAY);
INSERT INTO payments (member_id,subscription_id,amount,method,paid_at) VALUES (1,1,850,'carte bancaire',NOW()),(2,2,240,'espèces',NOW()),(3,3,90,'D17',NOW()-INTERVAL 1 MONTH),(4,4,850,'virement',NOW()-INTERVAL 2 MONTH),(5,5,90,'espèces',NOW()-INTERVAL 3 MONTH),(6,6,240,'Flouci',NOW()-INTERVAL 4 MONTH),(1,1,400,'espèces',NOW()-INTERVAL 5 MONTH);
INSERT INTO attendance (member_id,checked_in_at,result) VALUES (1,NOW(),'accepted'),(2,NOW(),'accepted'),(3,NOW(),'accepted'),(4,NOW(),'accepted');
