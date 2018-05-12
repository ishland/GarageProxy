Jenkinsfile (Declarative Pipeline)
pipeline {
    agent any

    stages {
        stage('Build') {
            steps {
                sh 'php build.php build normal'
                archiveArtifacts artifacts: '**/target/*.phar', fingerprint: true
            }
        }
    }
}
