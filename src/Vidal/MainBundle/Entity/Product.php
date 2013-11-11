<?php
namespace Vidal\MainBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Gedmo\Mapping\Annotation as Gedmo;
use Doctrine\Common\Collections\ArrayCollection;

/** @ORM\Entity(repositoryClass="ProductRepository") @ORM\Table(name="product") */
class Product
{
	/** @ORM\Id @ORM\Column(type="integer") @ORM\GeneratedValue */
	protected $ProductID;

	/** @ORM\Column(length=500) */
	protected $RusName;

	/** @ORM\Column(length=500) */
	protected $EngName;

	/** @ORM\Column(type="boolean") */
	protected $NonPrescriptionDrug = false;

	/**
	 * @ORM\ManyToOne(targetEntity="CountryEdition", inversedBy="products")
	 * @ORM\JoinColumn(name="CountryEditionCode", referencedColumnName="CountryEditionCode")
	 */
	protected $CountryEditionCode;

	/** @ORM\Column(type="datetime", nullable=true) */
	protected $RegistrationDate;

	/** @ORM\Column(type="datetime", nullable=true) */
	protected $DateOfCloseRegistration;

	/** @ORM\Column(length=50, nullable=true) */
	protected $RegistrationNumber;

	/** @ORM\Column(type="boolean") */
	protected $PPR;

	/** @ORM\Column(length=255) */
	protected $ZipInfo;

	/** @ORM\Column(type="text") */
	protected $Composition;

	/** @ORM\Column(type="datetime") @Gedmo\Timestampable(on="update") */
	protected $DateOfIncludingText;

	/**
	 * @ORM\ManyToOne(targetEntity="ProductType", inversedBy="products")
	 * @ORM\JoinColumn(name="ProductTypeCode", referencedColumnName="ProductTypeCode")
	 */
	protected $ProductTypeCode;

	/** @ORM\Column(type="boolean") */
	protected $ItsMultiProduct = false;

	/** @ORM\Column(type="integer", nullable=true) */
	protected $BelongMultiProductID;

	/**
	 * @ORM\ManyToOne(targetEntity="MarketStatus", inversedBy="products")
	 * @ORM\JoinColumn(name="MarketStatusID", referencedColumnName="MarketStatusID")
	 */
	protected $MarketStatusID;

	/** @ORM\Column(type="datetime", nullable=true) */
	protected $CheckingRegDate;

	/** @ORM\Column(type="integer", nullable=true) */
	protected $Personal;

	/** @ORM\Column(type="integer", nullable=true) */
	protected $m;

	/** @ORM\Column(type="boolean", nullable=true) */
	protected $GNVLS;

	/** @ORM\Column(type="boolean", nullable=true) */
	protected $DLO;

	/** @ORM\Column(length=10, nullable=true) */
	protected $List_AB;

	/** @ORM\Column(length=10, nullable=true) */
	protected $List_PKKN;

	/** @ORM\Column(type="boolean", nullable=true) */
	protected $StrongMeans;

	/** @ORM\Column(type="boolean", nullable=true) */
	protected $Poison;

	/** @ORM\Column(type="boolean", nullable=true) */
	protected $MinAs;

	/** @ORM\Column(length=50, nullable=true) */
	protected $ValidPeriod;

	/** @ORM\Column(type="text", nullable=true) */
	protected $StrCond;

	/**
	 * @ORM\ManyToMany(targetEntity="ATC", inversedBy="products")
	 * @ORM\JoinTable(name="product_atc",
	 *      joinColumns={@ORM\JoinColumn(name="ProductID", referencedColumnName="ProductID")},
	 *      inverseJoinColumns={@ORM\JoinColumn(name="ATCcode", referencedColumnName="ATCCode")})
	 */
	protected $atcCodes;

	/** @ORM\OneToMany(targetEntity="ProductDocument", mappedBy="ProductID") */
	protected $productDocument;

	/**
	 * @ORM\ManyToMany(targetEntity="ClPhGroups", inversedBy="products")
	 * @ORM\JoinTable(name="product_clphgroups",
	 *        joinColumns={@ORM\JoinColumn(name="ProductID", referencedColumnName="ProductID")},
	 *        inverseJoinColumns={@ORM\JoinColumn(name="ClPhGroupsID", referencedColumnName="ClPhGroupsID")})
	 */
	protected $clphGroups;

	/** @ORM\OneToMany(targetEntity="ProductCompany", mappedBy="ProductID") */
	protected $productCompany;

	/** @ORM\OneToMany(targetEntity="ProductPackage", mappedBy="ProductID") */
	protected $productPackages;

	/** @ORM\OneToMany(targetEntity="ProductItem", mappedBy="ProductID") */
	protected $productItems;

	/**
	 * @ORM\ManyToMany(targetEntity="MoleculeName", mappedBy="products")
	 * @ORM\JoinTable(name="product_moleculename",
	 *        joinColumns={@ORM\JoinColumn(name="ProductID", referencedColumnName="ProductID")},
	 *        inverseJoinColumns={@ORM\JoinColumn(name="MoleculeNameID", referencedColumnName="MoleculeNameID")})
	 */
	protected $moleculeNames;

	/** @ORM\OneToMany(targetEntity="ProductItemRoute", mappedBy="ProductID") */
	protected $productItemRoutes;

	public function __construct()
	{
		$this->atcCodes          = new ArrayCollection();
		$this->productDocument   = new ArrayCollection();
		$this->clphGroups        = new ArrayCollection();
		$this->productCompany    = new ArrayCollection();
		$this->productPackages   = new ArrayCollection();
		$this->productItems      = new ArrayCollection();
		$this->moleculeNames     = new ArrayCollection();
		$this->productItemRoutes = new ArrayCollection();
	}

	public function __toString()
	{
		return $this->RusName;
	}

	/**
	 * @param mixed $BelongMultiProductID
	 */
	public function setBelongMultiProductID($BelongMultiProductID)
	{
		$this->BelongMultiProductID = $BelongMultiProductID;
	}

	/**
	 * @return mixed
	 */
	public function getBelongMultiProductID()
	{
		return $this->BelongMultiProductID;
	}

	/**
	 * @param mixed $CheckingRegDate
	 */
	public function setCheckingRegDate($CheckingRegDate)
	{
		$this->CheckingRegDate = $CheckingRegDate;
	}

	/**
	 * @return mixed
	 */
	public function getCheckingRegDate()
	{
		return $this->CheckingRegDate;
	}

	/**
	 * @param mixed $Composition
	 */
	public function setComposition($Composition)
	{
		$this->Composition = $Composition;
	}

	/**
	 * @return mixed
	 */
	public function getComposition()
	{
		return $this->Composition;
	}

	/**
	 * @param mixed $CountryEditionCode
	 */
	public function setCountryEditionCode($CountryEditionCode)
	{
		$this->CountryEditionCode = $CountryEditionCode;
	}

	/**
	 * @return mixed
	 */
	public function getCountryEditionCode()
	{
		return $this->CountryEditionCode;
	}

	/**
	 * @param mixed $DLO
	 */
	public function setDLO($DLO)
	{
		$this->DLO = $DLO;
	}

	/**
	 * @return mixed
	 */
	public function getDLO()
	{
		return $this->DLO;
	}

	/**
	 * @param mixed $DateOfCloseRegistration
	 */
	public function setDateOfCloseRegistration($DateOfCloseRegistration)
	{
		$this->DateOfCloseRegistration = $DateOfCloseRegistration;
	}

	/**
	 * @return mixed
	 */
	public function getDateOfCloseRegistration()
	{
		return $this->DateOfCloseRegistration;
	}

	/**
	 * @param mixed $DateOfIncludingText
	 */
	public function setDateOfIncludingText($DateOfIncludingText)
	{
		$this->DateOfIncludingText = $DateOfIncludingText;
	}

	/**
	 * @return mixed
	 */
	public function getDateOfIncludingText()
	{
		return $this->DateOfIncludingText;
	}

	/**
	 * @param mixed $EngName
	 */
	public function setEngName($EngName)
	{
		$this->EngName = $EngName;
	}

	/**
	 * @return mixed
	 */
	public function getEngName()
	{
		return $this->EngName;
	}

	/**
	 * @param mixed $GNVLS
	 */
	public function setGNVLS($GNVLS)
	{
		$this->GNVLS = $GNVLS;
	}

	/**
	 * @return mixed
	 */
	public function getGNVLS()
	{
		return $this->GNVLS;
	}

	/**
	 * @param mixed $ItsMultiProduct
	 */
	public function setItsMultiProduct($ItsMultiProduct)
	{
		$this->ItsMultiProduct = $ItsMultiProduct;
	}

	/**
	 * @return mixed
	 */
	public function getItsMultiProduct()
	{
		return $this->ItsMultiProduct;
	}

	/**
	 * @param mixed $List_AB
	 */
	public function setListAB($List_AB)
	{
		$this->List_AB = $List_AB;
	}

	/**
	 * @return mixed
	 */
	public function getListAB()
	{
		return $this->List_AB;
	}

	/**
	 * @param mixed $List_PKKN
	 */
	public function setListPKKN($List_PKKN)
	{
		$this->List_PKKN = $List_PKKN;
	}

	/**
	 * @return mixed
	 */
	public function getListPKKN()
	{
		return $this->List_PKKN;
	}

	/**
	 * @param mixed $MarketStatusID
	 */
	public function setMarketStatusID($MarketStatusID)
	{
		$this->MarketStatusID = $MarketStatusID;
	}

	/**
	 * @return mixed
	 */
	public function getMarketStatusID()
	{
		return $this->MarketStatusID;
	}

	/**
	 * @param mixed $MinAs
	 */
	public function setMinAs($MinAs)
	{
		$this->MinAs = $MinAs;
	}

	/**
	 * @return mixed
	 */
	public function getMinAs()
	{
		return $this->MinAs;
	}

	/**
	 * @param mixed $NonPrescriptionDrug
	 */
	public function setNonPrescriptionDrug($NonPrescriptionDrug)
	{
		$this->NonPrescriptionDrug = $NonPrescriptionDrug;
	}

	/**
	 * @return mixed
	 */
	public function getNonPrescriptionDrug()
	{
		return $this->NonPrescriptionDrug;
	}

	/**
	 * @param mixed $PPR
	 */
	public function setPPR($PPR)
	{
		$this->PPR = $PPR;
	}

	/**
	 * @return mixed
	 */
	public function getPPR()
	{
		return $this->PPR;
	}

	/**
	 * @param mixed $Personal
	 */
	public function setPersonal($Personal)
	{
		$this->Personal = $Personal;
	}

	/**
	 * @return mixed
	 */
	public function getPersonal()
	{
		return $this->Personal;
	}

	/**
	 * @param mixed $Poison
	 */
	public function setPoison($Poison)
	{
		$this->Poison = $Poison;
	}

	/**
	 * @return mixed
	 */
	public function getPoison()
	{
		return $this->Poison;
	}

	/**
	 * @param mixed $ProductID
	 */
	public function setProductID($ProductID)
	{
		$this->ProductID = $ProductID;
	}

	/**
	 * @return mixed
	 */
	public function getProductID()
	{
		return $this->ProductID;
	}

	/**
	 * @param mixed $ProductTypeCode
	 */
	public function setProductTypeCode($ProductTypeCode)
	{
		$this->ProductTypeCode = $ProductTypeCode;
	}

	/**
	 * @return mixed
	 */
	public function getProductTypeCode()
	{
		return $this->ProductTypeCode;
	}

	/**
	 * @param mixed $RegistrationDate
	 */
	public function setRegistrationDate($RegistrationDate)
	{
		$this->RegistrationDate = $RegistrationDate;
	}

	/**
	 * @return mixed
	 */
	public function getRegistrationDate()
	{
		return $this->RegistrationDate;
	}

	/**
	 * @param mixed $RegistrationNumber
	 */
	public function setRegistrationNumber($RegistrationNumber)
	{
		$this->RegistrationNumber = $RegistrationNumber;
	}

	/**
	 * @return mixed
	 */
	public function getRegistrationNumber()
	{
		return $this->RegistrationNumber;
	}

	/**
	 * @param mixed $RusName
	 */
	public function setRusName($RusName)
	{
		$this->RusName = $RusName;
	}

	/**
	 * @return mixed
	 */
	public function getRusName()
	{
		return $this->RusName;
	}

	/**
	 * @param mixed $StrCond
	 */
	public function setStrCond($StrCond)
	{
		$this->StrCond = $StrCond;
	}

	/**
	 * @return mixed
	 */
	public function getStrCond()
	{
		return $this->StrCond;
	}

	/**
	 * @param mixed $StrongMeans
	 */
	public function setStrongMeans($StrongMeans)
	{
		$this->StrongMeans = $StrongMeans;
	}

	/**
	 * @return mixed
	 */
	public function getStrongMeans()
	{
		return $this->StrongMeans;
	}

	/**
	 * @param mixed $ValidPeriod
	 */
	public function setValidPeriod($ValidPeriod)
	{
		$this->ValidPeriod = $ValidPeriod;
	}

	/**
	 * @return mixed
	 */
	public function getValidPeriod()
	{
		return $this->ValidPeriod;
	}

	/**
	 * @param mixed $ZipInfo
	 */
	public function setZipInfo($ZipInfo)
	{
		$this->ZipInfo = $ZipInfo;
	}

	/**
	 * @return mixed
	 */
	public function getZipInfo()
	{
		return $this->ZipInfo;
	}

	/**
	 * @param mixed $m
	 */
	public function setM($m)
	{
		$this->m = $m;
	}

	/**
	 * @return mixed
	 */
	public function getM()
	{
		return $this->m;
	}

	/**
	 * @param mixed $atcCodes
	 */
	public function setAtcCodes(ArrayCollection $atcCodes)
	{
		$this->atcCodes = $atcCodes;
	}

	/**
	 * @return mixed
	 */
	public function getAtcCodes()
	{
		return $this->atcCodes;
	}

	/**
	 * @param mixed $productDocument
	 */
	public function setProductDocument(ArrayCollection $productDocument)
	{
		$this->productDocument = $productDocument;
	}

	/**
	 * @return mixed
	 */
	public function getProductDocument()
	{
		return $this->productDocument;
	}

	/**
	 * @param mixed $clphGroups
	 */
	public function setClphGroups(ArrayCollection $clphGroups)
	{
		$this->clphGroups = $clphGroups;
	}

	/**
	 * @return mixed
	 */
	public function getClphGroups()
	{
		return $this->clphGroups;
	}

	/**
	 * @param mixed $productCompany
	 */
	public function setProductCompany(ArrayCollection $productCompany)
	{
		$this->productCompany = $productCompany;
	}

	/**
	 * @return mixed
	 */
	public function getProductCompany()
	{
		return $this->productCompany;
	}

	/**
	 * @param mixed $productPackages
	 */
	public function setProductPackages(ArrayCollection $productPackages)
	{
		$this->productPackages = $productPackages;
	}

	/**
	 * @return mixed
	 */
	public function getProductPackages()
	{
		return $this->productPackages;
	}

	/**
	 * @param mixed $productItems
	 */
	public function setProductItems(ArrayCollection $productItems)
	{
		$this->productItems = $productItems;
	}

	/**
	 * @return mixed
	 */
	public function getProductItems()
	{
		return $this->productItems;
	}

	/**
	 * @param mixed $moleculeNames
	 */
	public function setMoleculeNames(ArrayCollection $moleculeNames)
	{
		$this->moleculeNames = $moleculeNames;
	}

	/**
	 * @return mixed
	 */
	public function getMoleculeNames()
	{
		return $this->moleculeNames;
	}

	/**
	 * @param mixed $productItemRoutes
	 */
	public function setProductItemRoutes(ArrayCollection $productItemRoutes)
	{
		$this->productItemRoutes = $productItemRoutes;
	}

	/**
	 * @return mixed
	 */
	public function getProductItemRoutes()
	{
		return $this->productItemRoutes;
	}
}