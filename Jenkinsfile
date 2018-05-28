node ("linux") {
    
    stage('Build and test') {
        git url:"https://github.com/gutty-club/GarageProxy.git"
        sh '''
        php build.php build normal
        cp ./target/GarageProxyServer.phar ./test/
        cd test
        php GarageProxyServer.phar start -d
        php GarageProxyServer.phar stop
        cd ..
        '''
        archiveArtifacts artifacts: '**/target/*.phar', fingerprint: true, onlyIfSuccessful: true 
    }
}