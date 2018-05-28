Jenkinsfile (Declarative Pipeline)
pipeline {
    agent 'linux'
    
    stages {
        stage('Build') {
            steps {
                sh '
                php build.php build normal
                php build.php build normal
                cp ./target/GarageProxyServer.phar ./test/
                cd test
                php GarageProxyServer.phar start -d
                php GarageProxyServer.phar stop
                cd ..
                '
                archiveArtifacts artifacts: '**/target/*.phar', fingerprint: true 
            }
        }
    }
}