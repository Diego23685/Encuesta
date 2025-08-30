use Proyecto_Encuesta;

-- Asegurar unicidad de correo para autenticación
alter table Respondents add unique key uq_respondents_email (Email);

-- Tabla de credenciales/roles vinculada a Respondents
create table if not exists Auth (
    Respondent_ID int primary key,
    Password_Hash varchar(255) not null,
    Role enum('admin','respondent') default 'respondent',
    Created_at datetime default current_timestamp,
    constraint Auth_FK1 foreign key (Respondent_ID) references Respondents(ID)
        on update cascade on delete cascade
);

-- Defaults útiles
alter table Surveys modify Created_at datetime default current_timestamp;
alter table Respondents modify Created_at datetime default current_timestamp;
alter table Submissions modify Started_at datetime default current_timestamp;
