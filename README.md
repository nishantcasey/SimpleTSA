SimpleTSA
=========

The Simple TSA Bayesian Classifier (forked from PHPir example)

This is a basic Naive Bayes Sentiment Classifier that works using a MySQL based corpus. 

Please note that this code will not work without a corpus being initialised and sufficient editing of the PHP
document allows for the class to read from the relevant DB. 

1. The DB must be passed to the constructor as an argument as an instance of an mysqli object.
2. The relevant db must contain two tables 'p3_words' and 'n3_words'
3. The training function must be ran twice with arguments of $file and $sentiment (as string with first letter capital)
4. Tokenisation, Hashtag and Stop Word removal is performed within the train and classify functions.


Please provide links to my website www.nishantcasey.com or www.simpletsa.com as well as www.phpir.com if used!

Thank you
