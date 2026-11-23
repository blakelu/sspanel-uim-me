<?php

declare(strict_types=1);

namespace Tests\Fixtures;

final class WebAuthnV524Fixture
{
    public const LEGACY_CREDENTIAL_ID = 'eHouz_Zi7-BmByHjJ_tx9h4a1WZsK4IzUmgGjkhyOodPGAyUqUp_B9yUkflXY3yHWsNtsrgCXQ3HjAIFUeZB-w';

    public const LEGACY_CREDENTIAL_SOURCE_JSON = '{"publicKeyCredentialId":"eHouz_Zi7-BmByHjJ_tx9h4a1WZsK4IzUmgGjkhyOodPGAyUqUp_B9yUkflXY3yHWsNtsrgCXQ3HjAIFUeZB-w","type":"public-key","transports":[],"attestationType":"none","trustPath":[],"aaguid":"00000000-0000-0000-0000-000000000000","credentialPublicKey":"pQECAyYgASFYIJV56vRrFusoDf9hm3iDmllcxxXzzKyO9WruKw4kWx7zIlgg_nq63l8IMJcIdKDJcXRh9hoz0L-nVwP1Oxil3_oNQYs","userHandle":"Zm9v","counter":100}';

    public const ASSERTION_REQUEST_OPTIONS_JSON = '{"challenge":"G0JbLLndef3a0Iy3S2sSQA8uO4SO_ze6FZMAuPI6-xI","timeout":60000,"rpId":"localhost","allowCredentials":[{"type":"public-key","id":"eHouz_Zi7-BmByHjJ_tx9h4a1WZsK4IzUmgGjkhyOodPGAyUqUp_B9yUkflXY3yHWsNtsrgCXQ3HjAIFUeZB-w"}],"userVerification":"preferred"}';

    public const WRONG_CHALLENGE_REQUEST_OPTIONS_JSON = '{"challenge":"eHh4eHh4eHh4eHh4eHh4eHh4eHh4eHh4eHh4eHh4eHg","timeout":60000,"rpId":"localhost","allowCredentials":[{"type":"public-key","id":"eHouz_Zi7-BmByHjJ_tx9h4a1WZsK4IzUmgGjkhyOodPGAyUqUp_B9yUkflXY3yHWsNtsrgCXQ3HjAIFUeZB-w"}],"userVerification":"preferred"}';

    public const ASSERTION_RESPONSE_JSON = '{"id":"eHouz_Zi7-BmByHjJ_tx9h4a1WZsK4IzUmgGjkhyOodPGAyUqUp_B9yUkflXY3yHWsNtsrgCXQ3HjAIFUeZB-w","type":"public-key","rawId":"eHouz/Zi7+BmByHjJ/tx9h4a1WZsK4IzUmgGjkhyOodPGAyUqUp/B9yUkflXY3yHWsNtsrgCXQ3HjAIFUeZB+w==","response":{"authenticatorData":"SZYN5YgOjGh0NBcPZHZgW4_krrmihjLHmVzzuoMdl2MBAAAAew","clientDataJSON":"eyJjaGFsbGVuZ2UiOiJHMEpiTExuZGVmM2EwSXkzUzJzU1FBOHVPNFNPX3plNkZaTUF1UEk2LXhJIiwiY2xpZW50RXh0ZW5zaW9ucyI6e30sImhhc2hBbGdvcml0aG0iOiJTSEEtMjU2Iiwib3JpZ2luIjoiaHR0cHM6Ly9sb2NhbGhvc3Q6ODQ0MyIsInR5cGUiOiJ3ZWJhdXRobi5nZXQifQ","signature":"MEUCIEY/vcNkbo/LdMTfLa24ZYLlMMVMRd8zXguHBvqud9AJAiEAwCwpZpvcMaqCrwv85w/8RGiZzE+gOM61ffxmgEDeyhM=","userHandle":null}}';

    public const REGISTRATION_RP_ID = 'tuleap-web.tuleap-aio-dev.docker';

    public const REGISTRATION_CREDENTIAL_ID = '31ivJEY3jmIoxWuGZ7pZjDuBW5n1PAMeG-e0drfhayCzOsuNaCG3PH43i-OebKT0jqY-bAFCEUh1JCCATSPa9N5QIUwwSlUQO9Pb5X1_yXJnY9q7GYfm3LvR4Yk6-HKj4MpBj6cbVOZyoLZQtd2lDEU7pSTcbTZBELQlODGSlbQ';

    public const REGISTRATION_OPTIONS_JSON = '{"rp":{"name":"Tuleap","id":"tuleap-web.tuleap-aio-dev.docker"},"user":{"name":"admin","id":"MTAx","displayName":"Site Administrator"},"challenge":"sNZel5OhIwA5vR4wdVkwiGHR6QEnNhYOqi97OHQrc2A","pubKeyCredParams":[{"type":"public-key","alg":-8},{"type":"public-key","alg":-7},{"type":"public-key","alg":-257}],"attestation":"none"}';

    public const REGISTRATION_RESPONSE_JSON = '{"clientExtensionResults":{},"id":"31ivJEY3jmIoxWuGZ7pZjDuBW5n1PAMeG-e0drfhayCzOsuNaCG3PH43i-OebKT0jqY-bAFCEUh1JCCATSPa9N5QIUwwSlUQO9Pb5X1_yXJnY9q7GYfm3LvR4Yk6-HKj4MpBj6cbVOZyoLZQtd2lDEU7pSTcbTZBELQlODGSlbQ","rawId":"31ivJEY3jmIoxWuGZ7pZjDuBW5n1PAMeG-e0drfhayCzOsuNaCG3PH43i-OebKT0jqY-bAFCEUh1JCCATSPa9N5QIUwwSlUQO9Pb5X1_yXJnY9q7GYfm3LvR4Yk6-HKj4MpBj6cbVOZyoLZQtd2lDEU7pSTcbTZBELQlODGSlbQ","response":{"attestationObject":"o2NmbXRkbm9uZWdhdHRTdG10oGhhdXRoRGF0YVkBBxawLfvD1MyjfrwvZRZlmxIhDbnhAYq58TqWkGOOpv2oRQAAAAEvwFefgRNH6rEWu1qNuSAqAIDfWK8kRjeOYijFa4ZnulmMO4FbmfU8Ax4b57R2t-FrILM6y41oIbc8fjeL455spPSOpj5sAUIRSHUkIIBNI9r03lAhTDBKVRA709vlfX_Jcmdj2rsZh-bcu9HhiTr4cqPgykGPpxtU5nKgtlC13aUMRTulJNxtNkEQtCU4MZKVtKMBY09LUAMnIGdFZDI1NTE5IZggGC0YVhiMGPEYGxjCGD8DFBiuGMAYLhhjCRjKGKYY3xhSGBgYnhhnGKEYIQwYPBjeGG0YwRidGIcY8Rjs","clientDataJSON":"eyJjaGFsbGVuZ2UiOiJzTlplbDVPaEl3QTV2UjR3ZFZrd2lHSFI2UUVuTmhZT3FpOTdPSFFyYzJBIiwib3JpZ2luIjoiaHR0cHM6Ly90dWxlYXAtd2ViLnR1bGVhcC1haW8tZGV2LmRvY2tlciIsInR5cGUiOiJ3ZWJhdXRobi5jcmVhdGUifQ"},"type":"public-key"}';
}
