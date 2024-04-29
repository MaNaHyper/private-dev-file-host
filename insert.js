'use strict';
const {google} = require('googleapis');
const {authenticate} = require('@google-cloud/local-auth');
const path = require('path');
const fs = require('fs');

const blogger = google.blogger('v3');

async function runSample() {


    const auth = await authenticate({
        keyfilePath: path.join(__dirname, 'credentials.json'),
        scopes: 'https://www.googleapis.com/auth/blogger',
      });

      await fs.writeFileSync('active.json', JSON.stringify(auth.credentials));


      google.options({auth});

      const res = await blogger.posts.insert({
        blogId: '5724385458254183108',
        requestBody: {
          title: 'Hello from the googleapis npm module!',
          content:
            'Visit https://github.com/google/google-api-nodejs-client to learn more!',
        },
      });
      console.log(res.data);

      console.log(auth)

      


}

async function myAuth() {
    let activeAuthFile = await fs.readFileSync('active.json');
    if(!activeAuthFile) {
         console.log("File error", activeAuthFile);
         const auth = await authenticate({
            keyfilePath: path.join(__dirname, 'credentials.json'),
            scopes: 'https://www.googleapis.com/auth/blogger',
        });


    }

}
runSample().catch(console.error);