Jenkinsfile (Declarative Pipeline)
pipeline {
    agent 'linux'
    
    stages {
        stage('Build') {
            steps {
                sh 'php build.php build normal'
                sh 'cp ./target/GarageProxyServer.phar ./test/'
                sh 'cd test'
                sh 'php GarageProxyServer.phar start -d'
                sh 'php GarageProxyServer.phar stop'
                sh 'cd ..'
                archiveArtifacts artifacts: '**/target/*.phar', fingerprint: true 
            }
        }
    }
}