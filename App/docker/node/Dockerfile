FROM node:0.10.46-onbuild

RUN mkdir /src

RUN npm install nodemon -g

WORKDIR /src

ADD ./package.json /src/package.json
RUN npm install

ADD ./socket.js /src/socket.js

EXPOSE 3000
EXPOSE 3043

CMD npm start