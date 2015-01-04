<?php

/**Naive Bayes classifier implementation by Nishant Casey,
adapted from phpir.com/bayesian-opinion-mining example
uses a mysql database connection which houses training data 
based on the sentiment140.com corpus
**/


class Classifier{
	//Stores an array of sentiments to iterate through
	private $sentiments = array('Positive', 'Negative');
	//Stores the count of tokens in a given sentiment
	private $tokensInSentiment = array('Positive'=>0, 'Negative'=>0); 	
	//Stores a count of all tokens in our database
	private $allTokensCount = 0;
	//database connection object, 
	public $mysqli;

	//Construct function takes in a mysqli database connection
	function __construct(mysqli $db){
		$this->mysqli = $db;
		//Calls the fixCounts function
		$this->fixCounts();
	}

	//Assigns values to $tokensInSentiment and $allTokensCount
	private function fixCounts(){
		$query = "SELECT count(*) as n_words from n_words";
		$result = mysqli_query($this->mysqli, $query);
		$ar = mysqli_fetch_assoc($result);
		$negative_count = $ar["n_words"];

		mysqli_free_result($result);
		
		$query = "SELECT count(*) as p_words from p_words";
		$result = mysqli_query($this->mysqli, $query);
		$ar = mysqli_fetch_assoc($result);
		$positive_count = $ar["p_words"];

		mysqli_free_result($result);

		$this->tokensInSentiment["Positive"] = $positive_count;
		$this->tokensInSentiment["Negative"] = $negative_count;
		$this->allTokensCount = $positive_count + $negative_count;
	}
	//Checks if document contains positive smiley
	private function isPosSmiley($value){
		$positives = ["😄","😃","😀","😊","😉","😍","😘",
			"😚","😗","😙","😜","😅","😎","😇","😏","👍","👌",
			"✊","✌","❤","😬","😂","♥","💖","🎉","💕","☺","(-:",":)","<3","😌","💓","=)"];


		$count = 0;
		foreach ($positives as $posemoji) {
			if (strpos($value, $posemoji, 0)){
				return 1;
			}			
		}
		return 0;
	}		
	//Checks if document contains negative smiley		
	private function isNegSmiley($value){

		$negatives = ["😢","😭","😪","😥","😰","😤","😖","😮","💔","😕"];
		$count = 0;
		foreach ($negatives as $negemoji) {
			if (strpos($value, $negemoji, 0)){
				return 1;			
			}
		}
		return 0;		
	}
	//Removes hashtags
	private function removeHashTag($token){
		if (strpos($token, "#", 0)==1){
			return substr($token, 1);
		}else{
			return $token;
		}
	}
	//Training function, takes in a filepath and a sentiment class 
	//trains to the mysql database.
	public function train($file, $sentiment){
		$fh = fopen($file, 'r');
		$i = 0;

		while($line = fgets($fh)){
			$i++;
			$tokens = $this->tokenise($line);
			foreach ($tokens as $token) {
				if ($this->isStopWord($token)){continue;}
				$token = $this->removeHashTag($token);

				/** Skip usernames **/
				if($token[0] === "@"){
					continue;
				}
				if($sentiment === "Positive"){
					$table = 'p3_words';
				}else{
					$table = 'n3_words';
				}


				$query = "SELECT * FROM {$table} WHERE word ='{$token}'";

				$result = mysqli_query($this->mysqli, $query);
				$ar  = mysqli_fetch_assoc($result);

				if(mysqli_num_rows($result) == 0){
					$query = "INSERT INTO {$table} (word, count) VALUES ('{$token}',0)";
					
					$result = mysqli_query($this->mysqli, $query);
					
				}
				

				$query = "UPDATE {$table} SET COUNT = COUNT+1 WHERE word = '{$token}'";

				mysqli_query($this->mysqli, $query);

				

				}
			}
			fclose($fh);
		}

	//Tokenises tweets in to an array of words
	private function tokenise($document){
		$document = strtolower($document);
		preg_match_all('/([a-zA-Z]|\xC3[\x80-\x96\x98-\xB6\xB8-\xBF]|\xC5[\x92\x93\xA0\xA1\xB8\xBD\xBE]){3,}/'
			, $document, $matches);
		return $matches[0];
	}
	//The classify function takes in a tweet and
	//returns a string for the relevant class
	public function classify($document){
		
		//FIrst we tokenise the tweet
		$tokens = $this->tokenise($document);
		//We initialise the sentiment score array that will store the 
		//score for each relevant class
		$sentimentScore = array();
		//The first sentiment based loop (occurs twice - once for each sentiment)
		foreach ($this->sentiments as $sentiment) {
				//If the class in question is positive use the positve words table
				if($sentiment === "Positive"){
					$table = 'p3_words';
				}else{
					//otherwise use the negative words table
					$table = 'n3_words';
				}

			//Set the sentiment score to one as we will be multiplying!!
			$sentimentScore[$sentiment] = 1;

			//This is the token based loop - it will occur once for each token (occurs twice in total for each token)
			foreach ($tokens as $token) {
				//Ignore any stop words
				if ($this->isStopWord($token)){continue;}
				//Remove hashtags
				$token = $this->removeHashTag($token);


				//FInd out if this token is in the database
				$query = "SELECT count FROM {$table} WHERE word = '{$token}'";
				//Perform mysql query
				$result = mysqli_query($this->mysqli, $query);
				//if there is no such word in the database set count to 0
				if (mysqli_num_rows($result) == 0){
					$count = 0;
				}else{
					//Otherwise set the count to the relevant amount from the db
					$ar = mysqli_fetch_assoc($result);
					$count = $ar["count"];
				}
				//For this particular sentiment class, multiply out each probability
				//+1 on the count for laplace
				//Divided by the amount of tokens in the relevant class + the total amount of tokens in both classes.
				$sentimentScore[$sentiment] *= ($count + 1) / ($this->tokensInSentiment[$sentiment] + $this->allTokensCount);
			}
			//Multiply the score by 0.5 which is our prior
			//log that to make the number more human readable (as more than likely a -E number )
			$sentimentScore[$sentiment] = log((0.5 * $sentimentScore[$sentiment]));

		}
		
		//Check for a positive smiley or neg smiley
		if (($this->isPosSmiley($document))>0){
			$sentimentScore["Positive"]+= 30;
		}
		if (($this->isNegSmiley($document))>0){
			$sentimentScore["Negative"]+= 30;
		}
		//Sort the array so that the biggest is first
		arsort($sentimentScore);
		$bigger =  key($sentimentScore);
		next($sentimentScore);
		$smaller = key($sentimentScore);
		//Divide the biggest by smallest to get a ratio
		if(($sentimentScore[$bigger]/$sentimentScore[$smaller]) > 0.995){
			//If it's greater than 0.995 then return Neutral
			return "Neutral";
		}
		//Reorganise the array
		arsort($sentimentScore);
		//Return the first key
		return key($sentimentScore);
	}

	private function isStopWord($word){

		$word = strtolower($word);

		$stopWords = [
		"a",
		"about",
		"above",
		"after",
		"again",
		"against",
		"all",
		"am",
		"an",
		"and",
		"any",
		"are",
		"aren't",
		"as",
		"at",
		"be",
		"because",
		"been",
		"before",
		"being",
		"below",
		"between",
		"both",
		"but",
		"by",
		"can't",
		"cannot",
		"could",
		"couldn't",
		"did",
		"didn't",
		"do",
		"does",
		"doesn't",
		"doing",
		"don't",
		"down",
		"during",
		"each",
		"few",
		"for",
		"from",
		"further",
		"had",
		"hadn't",
		"has",
		"hasn't",
		"have",
		"haven't",
		"having",
		"he",
		"he'd",
		"he'll",
		"he's",
		"her",
		"here",
		"here's",
		"hers",
		"herself",
		"him",
		"himself",
		"his",
		"how",
		"how's",
		"i",
		"i'd",
		"i'll",
		"i'm",
		"i've",
		"if",
		"in",
		"into",
		"is",
		"isn't",
		"it",
		"it's",
		"its",
		"itself",
		"let's",
		"me",
		"more",
		"most",
		"mustn't",
		"my",
		"myself",
		"no",
		"nor",
		"not",
		"of",
		"off",
		"on",
		"once",
		"only",
		"or",
		"other",
		"ought",
		"our",
		"ours",
		"ourselves",
		"out",
		"over",
		"own",
		"same",
		"shan't",
		"she",
		"she'd",
		"she'll",
		"she's",
		"should",
		"shouldn't",
		"so",
		"some",
		"such",
		"than",
		"that",
		"that's",
		"the",
		"their",
		"theirs",
		"them",
		"themselves",
		"then",
		"there",
		"there's",
		"these",
		"they",
		"they'd",
		"they'll",
		"they're",
		"they've",
		"this",
		"those",
		"through",
		"to",
		"too",
		"under",
		"until",
		"up",
		"very",
		"was",
		"wasn't",
		"we",
		"we'd",
		"we'll",
		"we're",
		"we've",
		"were",
		"weren't",
		"what",
		"what's",
		"when",
		"when's",
		"where",
		"where's",
		"which",
		"while",
		"who",
		"who's",
		"whom",
		"why",
		"why's",
		"with",
		"won't",
		"would",
		"wouldn't",
		"you",
		"you'd",
		"you'll",
		"you're",
		"you've",
		"your",
		"yours",
		"yourself",
		"yourselves"];

		if (in_array($word, $stopWords)){
			return true;
		}

		return false;
	}
}
