-- Base schema provided by user
drop database if exists Proyecto_Encuesta;
create database Proyecto_Encuesta;
use Proyecto_Encuesta;


-- Cada encuesta
create table Surveys (
    ID int auto_increment,
    Title varchar(50),
    Description varchar(200),
    Is_anonymous bool,
    Status varchar(50),
    Opens_at datetime,
    Closes_at datetime,
    Created_at datetime,
    
    constraint Surveys_PK primary key(ID)
);

-- Cada pregunta de la encuesta
create table Questions (
    ID int auto_increment,
    Survey_ID int,
    Position int,
    Text varchar(100),
    Type varchar(50),
    Is_required bool,
    Min_value float,
    Max_value float,
    
    constraint Questions_PK primary key(ID),
    
    -- 1 encuesta puede tener muchas preguntas, 1 pregunta solamente pertenece a 1 encuesta
    constraint Questions_FK1 foreign key(Survey_ID) references Surveys(ID)
    on update cascade on delete cascade
);

-- Cada persona que responde la encuesta
create table Respondents (
    ID int auto_increment,
    External_ID varchar(50),
    Name varchar(100),
    Email varchar(254),
    Created_at datetime,
    
    constraint Respondents_PK primary key(ID)
);

-- Cada envio como tal, que se realiza al finalizar la encuesta
create table Submissions (
    ID int auto_increment,
    Survey_ID int,
    Respondent_ID int,
    Started_at datetime,
    Submitted_at datetime,
    
    constraint Submissions_PK primary key(ID),
    
    -- 1 encuesta puede tener varios envios, 1 envio solamente tiene 1 encuesta
    constraint Submissions_FK1 foreign key(Survey_ID) references Surveys(ID)
    on update cascade on delete cascade,
    
    -- 1 usuario puede tener varios envios, 1 envio solamente tiene 1 usuario
    constraint Submissions_FK2 foreign key(Respondent_ID) references Respondents(ID)
    on update cascade on delete cascade
);

-- El banco de opciones o posibles respuestas
create table Choices (
    ID int auto_increment,
    Question_ID int,
    Position int,
    Label varchar(100),
    Value varchar(100),
    
    constraint Choices_PK primary key(ID),
    
    -- 1 pregunta puede tener varias opciones, 1 opcion solamente tiene 1 pregunta
    constraint Choices_FK1 foreign key(Question_ID) references Questions(ID)
    on update cascade on delete cascade
);

-- Las respuestas que el encuestado da como tal
create table Answers (
    ID int auto_increment,
    Submission_ID int,
    Question_ID int,
    Answer_text varchar(100),
    Answer_number float,
    Answer_date datetime,
    Selected_Choice_ID int,
    
    constraint Answers_PK primary key(ID),
    
    -- 1 envio puede tener varias respuestas, 1 respuesta solamente tiene 1 envio
    constraint Answers_FK1 foreign key(Submission_ID) references Submissions(ID)
    on update cascade on delete cascade,
    
    -- 1 pregunta puede tener varias respuestas, 1 respuesta solamente tiene 1 pregunta
    constraint Answers_FK2 foreign key(Question_ID) references Questions(ID)
    on update cascade on delete cascade,
    
    -- 1 opcion puede estar seleccionada por varias respuestas, 1 respuesta solamente tiene asociada 1 opcion
    constraint Answers_FK3 foreign key(Selected_Choice_ID) references Choices(ID)
    on update cascade on delete cascade
);

-- Tabla para unir Answers y Choices en una relacion muchos a muchos (N:N)
create table Answers_Choices (
    Answer_ID int,
    Choice_ID int,
    
    constraint Answers_Choices_PK primary key(Answer_ID, Choice_ID),
    
    constraint Answers_Choices_FK1 foreign key(Answer_ID) references Answers(ID)
    on update cascade on delete cascade,
    constraint Answers_Choices_FK2 foreign key(Choice_ID) references Choices(ID)
    on update cascade on delete cascade
);
