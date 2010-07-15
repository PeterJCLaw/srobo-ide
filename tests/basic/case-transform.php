<?php

test_equal(transformCase('bees tyres face', CASE_HUMAN_LOWER, CASE_SLASHES), 'bees/tyres/face', 'HL->S failed');
test_equal(transformCase('Bees Tyres Face', CASE_HUMAN_UPPER, CASE_UNDERSCORES), 'bees_tyres_face', 'HU->U failed');
test_equal(transformCase('BeesTyresFace', CASE_CAMEL_UCFIRST, CASE_UNDERSCORES), 'bees_tyres_face', 'CU->U failed');
test_equal(transformCase('beesTyresFace', CASE_CAMEL_LCFIRST, CASE_UNDERSCORES), 'bees_tyres_face', 'CL->U failed');
test_equal(transformCase('bees_tyres_face', CASE_UNDERSCORES, CASE_UNDERSCORES), 'bees_tyres_face', 'U->U failed');
test_equal(transformCase('bees/tyres/face', CASE_SLASHES, CASE_UNDERSCORES), 'bees_tyres_face', 'S->U failed');
test_equal(transformCase('bees_tyres_face', CASE_UNDERSCORES, CASE_SLASHES), 'bees/tyres/face', 'U->S failed');
test_equal(transformCase('bees_tyres_face', CASE_UNDERSCORES, CASE_CAMEL_LCFIRST), 'beesTyresFace', 'U->CL failed');
test_equal(transformCase('bees_tyres_face', CASE_UNDERSCORES, CASE_CAMEL_UCFIRST), 'BeesTyresFace', 'U->CU failed');
test_equal(transformCase('bees_tyres_face', CASE_UNDERSCORES, CASE_HUMAN_UPPER), 'Bees Tyres Face', 'U->HU failed');
test_equal(transformCase('bees_tyres_face', CASE_UNDERSCORES, CASE_HUMAN_LOWER), 'bees tyres face', 'U->HL failed');
